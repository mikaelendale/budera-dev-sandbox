<?php

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\Transactional\CompanyInvitationNotification;
use Illuminate\Support\Facades\Notification;

test('company owner can send an invitation email', function () {
    Notification::fake();

    $owner = User::factory()->withCompany('Acme')->create();

    $this->actingAs($owner)
        ->post(route('company.invitations.store'), [
            'email' => 'newdev@example.com',
        ])
        ->assertRedirect();

    Notification::assertSentOnDemand(CompanyInvitationNotification::class, function (CompanyInvitationNotification $n): bool {
        return $n->inviteeEmail === 'newdev@example.com';
    });

    expect(CompanyInvitation::query()->where('email', 'newdev@example.com')->exists())->toBeTrue();
});

test('company developer cannot send invitations', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $developer = User::factory()->create();
    assignTeamRole($developer, 'company_developer', $company);

    $this->actingAs($developer)
        ->post(route('company.invitations.store'), [
            'email' => 'x@example.com',
        ])
        ->assertForbidden();
});

test('cannot invite an email that is already a member', function () {
    Notification::fake();

    $owner = User::factory()->withCompany('Acme')->create();

    $this->actingAs($owner)
        ->post(route('company.invitations.store'), [
            'email' => $owner->email,
        ])
        ->assertSessionHasErrors('email');
});

test('cannot create duplicate pending invitation for same email', function () {
    Notification::fake();

    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    CompanyInvitation::query()->create([
        'company_id' => $company->id,
        'email' => 'pending@example.com',
        'token' => str_repeat('a', 64),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)
        ->post(route('company.invitations.store'), [
            'email' => 'pending@example.com',
        ])
        ->assertSessionHasErrors('email');
});

test('company owner can revoke a pending invitation', function () {
    $owner = User::factory()->withCompany('Acme')->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $invitation = CompanyInvitation::query()->create([
        'company_id' => $company->id,
        'email' => 'gone@example.com',
        'token' => str_repeat('b', 64),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)
        ->delete(route('company.invitations.destroy', $invitation))
        ->assertRedirect();

    expect(CompanyInvitation::query()->find($invitation->id))->toBeNull();
});
