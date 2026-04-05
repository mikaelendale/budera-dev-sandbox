<?php

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;

test('dashboard redirects to onboarding when user has no company membership', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding'));
});

test('dashboard redirects unverified end users to kyc', function () {
    $user = User::factory()->endUser()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('user.kyc.show'));
});

test('dashboard redirects kyc verified end users to wallet', function () {
    $user = User::factory()->kycVerified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('user.wallet.index'));
});

test('budera admin can visit dashboard without a company', function () {
    $user = User::factory()->buderaAdmin()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('user can create a company from onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.company.store'), [
            'name' => 'Acme AI',
        ])
        ->assertRedirect(route('dashboard'));

    expect(Company::query()->where('name', 'Acme AI')->where('owner_id', $user->id)->exists())->toBeTrue();
    expect($user->fresh()->hasCompanyMembership())->toBeTrue();
});

test('user can accept an invitation as company developer', function () {
    $owner = User::factory()->withCompany('Parent Co')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $invitee = User::factory()->create([
        'email' => 'dev@example.com',
    ]);

    $invitation = CompanyInvitation::query()->create([
        'company_id' => $company->id,
        'email' => 'dev@example.com',
        'token' => 'test-invite-token-'.str_repeat('a', 40),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($invitee)
        ->get(route('invitations.accept', ['token' => $invitation->token]))
        ->assertRedirect(route('dashboard'));

    expect($invitee->fresh()->hasCompanyMembership())->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});
