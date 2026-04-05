<?php

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\User;
use App\States\BankLink\BankLinkFailed;
use App\States\BankLink\BankLinkVerified;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function phase14BankLinkApiKey(User $owner, array $abilities): string
{
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $plain = 'sk_sandbox_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => $abilities,
        'metadata' => [],
    ]);

    return $plain;
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('hosted bank link session mint returns token and url for company member', function (): void {
    $owner = User::factory()->withCompany('Acme Hosted')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $member = User::factory()->create();
    assignTeamRole($member, 'company_developer', $company);

    $plain = phase14BankLinkApiKey($owner, ['wallet:link', 'wallet:read']);

    $response = $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'end_user_id' => $member->id,
            'environment' => 'sandbox',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['session_token', 'hosted_url', 'data'])
        ->assertJsonPath('data.status', 'initiated');

    $token = $response->json('session_token');
    expect(is_string($token))->toBeTrue()
        ->and(strlen($token))->toBeGreaterThan(40);
});

test('hosted bank link rejects end user not in company', function (): void {
    $owner = User::factory()->withCompany('Acme Hosted')->create();
    $outsider = User::factory()->create();
    $plain = phase14BankLinkApiKey($owner, ['wallet:link', 'wallet:read']);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'end_user_id' => $outsider->id,
            'environment' => 'sandbox',
        ])
        ->assertForbidden();
});

test('public bank link flow completes in sandbox', function (): void {
    $owner = User::factory()->withCompany('Acme Flow')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $member = User::factory()->create();
    assignTeamRole($member, 'company_developer', $company);

    $plain = phase14BankLinkApiKey($owner, ['wallet:link', 'wallet:read']);

    $create = $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', [
            'end_user_id' => $member->id,
            'environment' => 'sandbox',
        ]);

    $create->assertCreated();
    $sessionToken = $create->json('session_token');

    $this->get(route('bank-link.show', ['sessionToken' => $sessionToken]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bank-link/session')
            ->where('step', 'credentials'));

    $this->post(route('bank-link.credentials', ['sessionToken' => $sessionToken]), [
        'routing_number' => '021000021',
        'account_number' => '123456789012',
        'bank_slug' => 'chase',
    ])->assertRedirect(route('bank-link.show', ['sessionToken' => $sessionToken]));

    $this->get(route('bank-link.show', ['sessionToken' => $sessionToken]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('step', 'verify'));

    $this->post(route('bank-link.verify', ['sessionToken' => $sessionToken]), [
        'amount_first_cents' => 12,
        'amount_second_cents' => 34,
    ])->assertRedirect(route('bank-link.show', ['sessionToken' => $sessionToken]));

    $link = BankLink::query()->where('user_id', $member->id)->latest()->first();
    expect($link)->not->toBeNull()
        ->and($link->status)->toBeInstanceOf(BankLinkVerified::class);
});

test('bank link verification locks after three wrong attempts', function (): void {
    $owner = User::factory()->withCompany('Acme Lock')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $member = User::factory()->create();
    assignTeamRole($member, 'company_developer', $company);

    $plain = phase14BankLinkApiKey($owner, ['wallet:link', 'wallet:read']);

    $sessionToken = $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', ['end_user_id' => $member->id])
        ->json('session_token');

    $this->post(route('bank-link.credentials', ['sessionToken' => $sessionToken]), [
        'routing_number' => '021000021',
        'account_number' => '123456789012',
    ]);

    for ($i = 0; $i < 3; $i++) {
        $this->post(route('bank-link.verify', ['sessionToken' => $sessionToken]), [
            'amount_first_cents' => 1,
            'amount_second_cents' => 2,
        ]);
    }

    $link = BankLink::query()->where('user_id', $member->id)->latest()->first();
    expect($link)->not->toBeNull()
        ->and($link->status)->toBeInstanceOf(BankLinkFailed::class);

    $this->get(route('bank-link.show', ['sessionToken' => $sessionToken]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('step', 'locked'));
});

test('expired bank link session shows expired step', function (): void {
    $owner = User::factory()->withCompany('Acme Exp')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();
    $member = User::factory()->create();
    assignTeamRole($member, 'company_developer', $company);

    $plain = phase14BankLinkApiKey($owner, ['wallet:link', 'wallet:read']);

    $sessionToken = $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/v1/bank-links', ['end_user_id' => $member->id])
        ->json('session_token');

    $link = BankLink::query()->where('user_id', $member->id)->latest()->firstOrFail();
    $link->forceFill(['session_expires_at' => now()->subHour()])->save();

    $this->get(route('bank-link.show', ['sessionToken' => $sessionToken]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('step', 'expired'));
});
