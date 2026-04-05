<?php

use App\Models\BankLink;
use App\Models\Company;
use App\Models\LedgerEntry;
use App\Models\OAuthClient;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\Models\WalletOauthGrant;
use Illuminate\Support\Str;
use Laravel\Passport\Token;

beforeEach(function (): void {
    $hasPersonal = OAuthClient::query()
        ->where('revoked', false)
        ->get()
        ->contains(fn (OAuthClient $c): bool => $c->hasGrantType('personal_access'));

    if (! $hasPersonal) {
        OAuthClient::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Personal Access Client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);
    }
});

/**
 * Create an OAuth client + token for a user and return the token model.
 */
function createAgentToken(User $user, string $clientName, array $scopes = ['wallet:read'], ?Company $company = null): Token
{
    $client = OAuthClient::query()->create([
        'id' => (string) Str::uuid(),
        'name' => $clientName,
        'secret' => Str::random(40),
        'provider' => 'users',
        'company_id' => $company?->getKey(),
        'redirect_uris' => ['http://localhost/callback'],
        'grant_types' => ['authorization_code'],
        'revoked' => false,
    ]);

    $token = new Token;
    $token->id = hash('sha256', Str::random(80));
    $token->user_id = $user->id;
    $token->client_id = $client->id;
    $token->scopes = $scopes;
    $token->revoked = false;
    $token->expires_at = now()->addYear();
    $token->save();

    return $token;
}

test('guest is redirected from my-wallet', function () {
    $this->get(route('user.wallet.index'))
        ->assertRedirect(route('login'));
});

test('guest is redirected from my-agents', function () {
    $this->get(route('user.agents.index'))
        ->assertRedirect(route('login'));
});

test('non-end users cannot access end-user wallet routes', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('user.wallet.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('user.agents.index'))
        ->assertForbidden();
});

test('unverified end user is redirected from wallet to kyc', function () {
    $user = User::factory()->endUser()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('user.wallet.index'))
        ->assertRedirect(route('user.kyc.show'));
});

test('unverified end user is redirected from agents to kyc', function () {
    $user = User::factory()->endUser()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('user.agents.index'))
        ->assertRedirect(route('user.kyc.show'));
});

test('unverified end user can see the kyc page', function () {
    $user = User::factory()->endUser()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('user.kyc.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('user/verify-identity'));
});

test('sandbox pending kyc completes when user revisits verify identity page', function () {
    config()->set('budera.sandbox.allow_force_kyc_approve', false);

    $user = User::factory()->endUser()->create(['email_verified_at' => now()]);
    $wallet = WalletAccount::factory()->create([
        'company_id' => null,
        'user_id' => $user->id,
        'environment' => 'sandbox',
        'status' => 'pending',
        'partner_account_id' => 'mock_acct_stuck',
    ]);

    WalletKycVerification::query()->create([
        'wallet_account_id' => $wallet->id,
        'status' => 'pending',
        'session_token' => Str::random(48),
    ]);

    expect($user->fresh()->isKycVerified())->toBeFalse();

    $this->actingAs($user)
        ->get(route('user.kyc.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/verify-identity')
            ->where('step', 'approved'));

    expect($user->fresh()->isKycVerified())->toBeTrue();
});

test('missing kyc row is backfilled when end user visits verify identity', function () {
    config()->set('budera.sandbox.allow_force_kyc_approve', true);

    $user = User::factory()->endUser()->create(['email_verified_at' => now()]);
    $wallet = WalletAccount::factory()->personal()->pendingWithoutPartnerAccount()->create([
        'user_id' => $user->id,
        'partner_account_id' => 'mock_acct_legacy',
    ]);

    expect(WalletKycVerification::query()->where('wallet_account_id', $wallet->id)->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('user.kyc.show'))
        ->assertOk();

    expect(WalletKycVerification::query()->where('wallet_account_id', $wallet->id)->count())->toBe(1);

    $this->actingAs($user)->post(route('user.kyc.submit'), [
        'legal_name' => 'Legacy User',
        'date_of_birth' => '1991-02-20',
        'address_line_1' => '99 Oak Ave',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
        'ssn_last4' => '5678',
    ])->assertRedirect(route('user.kyc.show'));

    expect($user->fresh()->isKycVerified())->toBeTrue();
});

test('kyc form submission auto-approves in sandbox and activates wallet', function () {
    config()->set('budera.sandbox.allow_force_kyc_approve', true);

    $this->post(route('register.store'), [
        'name' => 'KYC Test',
        'email' => 'kyc-test@example.com',
        'password' => 'password',
        'user_type' => 'end_user',
    ]);

    $user = User::query()->where('email', 'kyc-test@example.com')->firstOrFail();

    expect($user->isKycVerified())->toBeFalse();

    $this->actingAs($user)->post(route('user.kyc.submit'), [
        'legal_name' => 'Jane Doe',
        'date_of_birth' => '1990-01-15',
        'address_line_1' => '123 Main St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94105',
        'ssn_last4' => '1234',
    ])->assertRedirect(route('user.kyc.show'));

    $user->refresh();
    expect($user->isKycVerified())->toBeTrue();

    $wallet = $user->personalWallet;
    expect($wallet->status->getValue())->toBe('active');
});

test('end user sees wallet dashboard with ai company oauth access', function () {
    $user = User::factory()->kycVerified()->create(['email_verified_at' => now()]);
    $company = Company::factory()->create(['name' => 'Acme AI']);

    $wallet = WalletAccount::query()->withoutGlobalScopes()
        ->where('user_id', $user->id)
        ->whereNull('company_id')
        ->firstOrFail();

    $wallet->update(['balance_cents' => 125000]);

    BankLink::factory()->mockBank()->create([
        'user_id' => $user->id,
        'wallet_account_id' => $wallet->id,
    ]);

    $token = createAgentToken($user, 'Acme Agent Runtime', ['wallet:read', 'wallet:pay'], $company);

    $grant = WalletOauthGrant::query()
        ->withoutGlobalScopes()
        ->where('oauth_access_token_id', $token->id)
        ->firstOrFail();

    $grant->update([
        'company_id' => $company->id,
        'wallet_account_id' => $wallet->id,
    ]);

    $response = $this->actingAs($user)->get(route('user.wallet.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/wallet/index')
            ->where('wallet.public_id', $wallet->public_id)
            ->where('wallet.balance_cents', 125000)
            ->has('wallet.bank_links', 1)
            ->has('companyAccess', 1)
            ->where('companyAccess.0.company_name', 'Acme AI')
            ->where('companyAccess.0.connection_count', 1)
            ->has('connections', 1)
            ->where('connections.0.client_name', 'Acme Agent Runtime')
        );
});

test('end user sees their authorized agents', function () {
    $user = User::factory()->kycVerified()->create(['email_verified_at' => now()]);

    $company = Company::factory()->create();
    $token = createAgentToken($user, 'Test Agent', ['wallet:read', 'wallet:pay'], $company);

    $wallet = WalletAccount::query()->withoutGlobalScopes()
        ->where('user_id', $user->id)
        ->whereNull('company_id')
        ->firstOrFail();

    $wallet->update(['balance_cents' => 50000]);

    $grant = WalletOauthGrant::query()
        ->withoutGlobalScopes()
        ->where('oauth_access_token_id', $token->id)
        ->firstOrFail();

    $grant->update([
        'company_id' => $company->id,
        'wallet_account_id' => $wallet->id,
    ]);

    LedgerEntry::factory()->debit(5000, 45000)->create([
        'wallet_account_id' => $wallet->id,
    ]);

    $response = $this->actingAs($user)->get(route('user.agents.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/agents/index')
            ->has('agents', 1)
            ->where('agents.0.client_name', 'Test Agent')
            ->where('agents.0.wallet_balance_cents', 50000)
            ->where('agents.0.total_spent_cents', 5000)
        );
});

test('end user can view agent detail page', function () {
    $user = User::factory()->kycVerified()->create(['email_verified_at' => now()]);

    $company = Company::factory()->create();
    $token = createAgentToken($user, 'Detail Agent', ['wallet:read'], $company);

    $wallet = WalletAccount::query()->withoutGlobalScopes()
        ->where('user_id', $user->id)
        ->whereNull('company_id')
        ->firstOrFail();

    $wallet->update(['balance_cents' => 100000]);

    $grant = WalletOauthGrant::query()
        ->withoutGlobalScopes()
        ->where('oauth_access_token_id', $token->id)
        ->firstOrFail();

    $grant->update([
        'company_id' => $company->id,
        'wallet_account_id' => $wallet->id,
    ]);

    LedgerEntry::factory()->credit(100000, 100000)->create([
        'wallet_account_id' => $wallet->id,
    ]);

    $response = $this->actingAs($user)->get(route('user.agents.show', ['token' => $token->id]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/agents/show')
            ->where('agent.client_name', 'Detail Agent')
            ->where('wallet.balance_cents', 100000)
            ->has('ledgerEntries', 1)
        );
});

test('end user cannot see another user\'s agents', function () {
    $owner = User::factory()->kycVerified()->create(['email_verified_at' => now()]);
    $other = User::factory()->kycVerified()->create(['email_verified_at' => now()]);

    $token = createAgentToken($owner, 'Owner Agent', ['wallet:read']);

    $this->actingAs($other)
        ->get(route('user.agents.show', ['token' => $token->id]))
        ->assertNotFound();
});
