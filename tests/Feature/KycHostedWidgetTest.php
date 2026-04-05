<?php

use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\States\WalletKycVerification\WalletKycVerificationApproved;
use App\States\WalletKycVerification\WalletKycVerificationPending;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createKycVerification(array $overrides = [], ?WalletAccount $wallet = null): WalletKycVerification
{
    $wallet ??= WalletAccount::factory()->create();

    return WalletKycVerification::query()->create(array_merge([
        'wallet_account_id' => $wallet->id,
        'status' => 'not_started',
        'session_token' => Str::random(48),
        'session_expires_at' => now()->addHour(),
    ], $overrides));
}

test('kyc session page renders with valid token', function (): void {
    $verification = createKycVerification();

    $this->get(route('kyc.show', ['sessionToken' => $verification->session_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kyc/session')
            ->where('step', 'identity')
            ->where('sessionToken', $verification->session_token));
});

test('invalid kyc session token returns 404', function (): void {
    $this->get(route('kyc.show', ['sessionToken' => 'invalid-token-that-does-not-exist']))
        ->assertNotFound();
});

test('identity form submission transitions to pending for live wallet without force flag', function (): void {
    config()->set('budera.sandbox.allow_force_kyc_approve', false);

    $liveWallet = WalletAccount::factory()->create(['environment' => 'live']);
    $verification = createKycVerification([], $liveWallet);

    $this->post(route('kyc.submit', ['sessionToken' => $verification->session_token]), [
        'legal_name' => 'Jane Doe',
        'date_of_birth' => '1990-01-15',
        'address_line_1' => '123 Main St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94105',
        'ssn_last4' => '1234',
    ])->assertRedirect(route('kyc.show', ['sessionToken' => $verification->session_token]));

    $verification->refresh();
    expect($verification->status)->toBeInstanceOf(WalletKycVerificationPending::class);
});

test('identity form submission auto approves sandbox wallet without force flag', function (): void {
    config()->set('budera.sandbox.allow_force_kyc_approve', false);

    $verification = createKycVerification();

    $this->post(route('kyc.submit', ['sessionToken' => $verification->session_token]), [
        'legal_name' => 'Jane Doe',
        'date_of_birth' => '1990-01-15',
        'address_line_1' => '123 Main St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94105',
        'ssn_last4' => '1234',
    ])->assertRedirect();

    $verification->refresh();
    expect($verification->status)->toBeInstanceOf(WalletKycVerificationApproved::class);
});

test('identity form submission auto approves live wallet when sandbox force kyc is enabled', function (): void {
    config()->set('budera.sandbox.allow_force_kyc_approve', true);

    $liveWallet = WalletAccount::factory()->create(['environment' => 'live']);
    $verification = createKycVerification([], $liveWallet);

    $this->post(route('kyc.submit', ['sessionToken' => $verification->session_token]), [
        'legal_name' => 'Jane Doe',
        'date_of_birth' => '1990-01-15',
        'address_line_1' => '123 Main St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94105',
        'ssn_last4' => '1234',
    ])->assertRedirect();

    $verification->refresh();
    expect($verification->status)->toBeInstanceOf(WalletKycVerificationApproved::class);
});

test('expired session shows expired step', function (): void {
    $verification = createKycVerification([
        'session_expires_at' => now()->subHour(),
    ]);

    $this->get(route('kyc.show', ['sessionToken' => $verification->session_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kyc/session')
            ->where('step', 'expired'));
});

test('expired session cannot submit identity form', function (): void {
    $verification = createKycVerification([
        'session_expires_at' => now()->subHour(),
    ]);

    $this->post(route('kyc.submit', ['sessionToken' => $verification->session_token]), [
        'legal_name' => 'Jane Doe',
        'date_of_birth' => '1990-01-15',
        'address_line_1' => '123 Main St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94105',
        'ssn_last4' => '1234',
    ])->assertRedirect();

    $verification->refresh();
    expect($verification->status->getValue())->toBe('not_started');
});

test('already submitted verification cannot resubmit', function (): void {
    $verification = createKycVerification([
        'status' => 'pending',
    ]);

    $this->post(route('kyc.submit', ['sessionToken' => $verification->session_token]), [
        'legal_name' => 'Jane Doe',
        'date_of_birth' => '1990-01-15',
        'address_line_1' => '123 Main St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94105',
        'ssn_last4' => '1234',
    ])->assertRedirect();

    $verification->refresh();
    expect($verification->status)->toBeInstanceOf(WalletKycVerificationPending::class);
});
