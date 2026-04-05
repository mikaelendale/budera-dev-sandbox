<?php

use App\Models\DomainAuditLog;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Audit\AuthorizationLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

test('incoming X-Correlation-Id is stored on domain audit log for invitations', function (): void {
    Notification::fake();

    $owner = User::factory()->withCompany()->create();

    actingAs($owner)
        ->withHeader('X-Correlation-Id', 'corr-phase10-invite')
        ->post(route('company.invitations.store'), [
            'email' => 'invite-phase10@example.com',
        ])
        ->assertRedirect();

    expect(DomainAuditLog::query()
        ->where('action', 'company_invitation.sent')
        ->where('correlation_id', 'corr-phase10-invite')
        ->exists())->toBeTrue();
});

test('incoming X-Correlation-Id is stored on domain audit for oauth client create', function (): void {
    $owner = User::factory()->withCompany()->create();

    actingAs($owner)
        ->withHeader('X-Correlation-Id', 'corr-phase10-oauth')
        ->post(route('company.oauth-apps.store'), [
            'name' => 'Phase 10 Test Client',
            'redirect_uri' => 'https://example.test/oauth/callback',
            'is_public' => true,
        ])
        ->assertRedirect();

    expect(DomainAuditLog::query()
        ->where('action', 'oauth_client.created')
        ->where('correlation_id', 'corr-phase10-oauth')
        ->exists())->toBeTrue();
});

test('wallet spend policy update writes domain audit', function (): void {
    $owner = User::factory()->withCompany()->create();
    $company = $owner->firstCompany();
    expect($company)->not->toBeNull();

    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->getKey(),
        'user_id' => $owner->getKey(),
    ]);

    actingAs($owner)
        ->patch(route('company.wallets.policy.update', $wallet), [
            'per_tx_limit_usd' => 250,
            'velocity_sensitivity' => 'high',
        ])
        ->assertRedirect(route('company.wallets.policy.show', $wallet));

    expect(DomainAuditLog::query()
        ->where('action', 'wallet_spend.policy_created')
        ->where('resource_type', 'policies')
        ->exists())->toBeTrue();
});

test('authorization ledger rows are append-only at the model and database layers', function (): void {
    $user = User::factory()->create();
    $entry = app(AuthorizationLedgerService::class)->recordAuthorization(
        'ach_standing',
        $user,
        null,
        (string) config('budera.ach.standing_authorization_text'),
        '10.0.0.1',
        'PHPUnit',
        'sandbox',
        ['bank_link_id' => '999'],
    );

    $fresh = $entry->fresh();
    expect($fresh)->not->toBeNull();

    expect(fn () => $fresh->update(['stream' => 'tampered']))->toThrow(RuntimeException::class);

    expect(fn () => $fresh->delete())->toThrow(RuntimeException::class);

    expect(fn () => DB::table('authorization_ledger')
        ->where('id', $fresh->getKey())
        ->update(['stream' => 'tampered']))->toThrow(QueryException::class);
});

test('authorization ledger export outputs verifiable json bundle', function (): void {
    $user = User::factory()->create();
    $entry = app(AuthorizationLedgerService::class)->recordAuthorization(
        'ach_standing',
        $user,
        null,
        (string) config('budera.ach.standing_authorization_text'),
        '10.0.0.2',
        'PHPUnit',
        'sandbox',
        ['bank_link_id' => '1000'],
    );

    Artisan::call('authorization-ledger:export', ['entry_id' => $entry->getKey()]);
    $decoded = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($decoded['signature_valid'] ?? null)->toBeTrue();
    expect($decoded['entry']['id'] ?? null)->toBe($entry->getKey());
});
