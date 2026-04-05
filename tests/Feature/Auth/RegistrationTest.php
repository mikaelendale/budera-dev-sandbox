<?php

use App\Models\BankLink;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'user_type' => 'developer',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('onboarding', absolute: false));
});

test('developer registration does not provision a personal wallet', function () {
    $this->post(route('register.store'), [
        'name' => 'Dev User',
        'email' => 'dev@example.com',
        'password' => 'password',
        'user_type' => 'developer',
    ]);

    $user = User::query()->where('email', 'dev@example.com')->firstOrFail();

    expect($user->personalWallet)->toBeNull();
    expect(WalletAccount::withoutGlobalScopes()->where('user_id', $user->getKey())->count())->toBe(0);
});

test('new end users can register and are redirected to kyc', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'End User',
        'email' => 'end-user@example.com',
        'password' => 'password',
        'user_type' => 'end_user',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('user.kyc.show', absolute: false));

    $user = User::query()->where('email', 'end-user@example.com')->firstOrFail();

    expect($user->isEndUser())->toBeTrue();
});

test('end user registration auto-provisions a pending personal wallet', function () {
    $this->post(route('register.store'), [
        'name' => 'Wallet User',
        'email' => 'wallet@example.com',
        'password' => 'password',
        'user_type' => 'end_user',
    ]);

    $user = User::query()->where('email', 'wallet@example.com')->firstOrFail();
    $wallet = $user->personalWallet;

    expect($wallet)->not->toBeNull()
        ->and($wallet->company_id)->toBeNull()
        ->and($wallet->isPersonal())->toBeTrue()
        ->and($wallet->environment)->toBe('sandbox')
        ->and($wallet->status->getValue())->toBe('pending')
        ->and($wallet->balance_cents)->toBe(0)
        ->and($wallet->partner_account_id)->toStartWith('mock_acct_');
});

test('end user registration creates a kyc verification record', function () {
    $this->post(route('register.store'), [
        'name' => 'Kyc User',
        'email' => 'kyc@example.com',
        'password' => 'password',
        'user_type' => 'end_user',
    ]);

    $user = User::query()->where('email', 'kyc@example.com')->firstOrFail();
    $wallet = $user->personalWallet;

    $kyc = WalletKycVerification::query()->where('wallet_account_id', $wallet->getKey())->first();

    expect($kyc)->not->toBeNull()
        ->and($kyc->status->getValue())->toBe('not_started')
        ->and($kyc->session_token)->not->toBeNull()
        ->and($kyc->session_expires_at)->not->toBeNull();
});

test('end user registration auto-provisions a mock bank link', function () {
    $this->post(route('register.store'), [
        'name' => 'Bank User',
        'email' => 'bank@example.com',
        'password' => 'password',
        'user_type' => 'end_user',
    ]);

    $user = User::query()->where('email', 'bank@example.com')->firstOrFail();
    $wallet = $user->personalWallet;
    $bankLink = BankLink::withoutGlobalScopes()
        ->where('user_id', $user->getKey())
        ->first();

    expect($bankLink)->not->toBeNull()
        ->and($bankLink->wallet_account_id)->toBe($wallet->getKey())
        ->and($bankLink->bank_slug)->toBe('mock-bank')
        ->and($bankLink->status->getValue())->toBe('verified')
        ->and($bankLink->verified_at)->not->toBeNull();
});

test('end user personal wallet has exactly one bank link', function () {
    $this->post(route('register.store'), [
        'name' => 'Single Bank User',
        'email' => 'single-bank@example.com',
        'password' => 'password',
        'user_type' => 'end_user',
    ]);

    $user = User::query()->where('email', 'single-bank@example.com')->firstOrFail();
    $wallet = $user->personalWallet;

    expect($wallet->bankLinks)->toHaveCount(1);
});
