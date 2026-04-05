<?php

use App\Models\User;
use App\Providers\HorizonServiceProvider;
use Illuminate\Support\Facades\Gate;

it('registers horizon service provider', function () {
    expect(app()->getProviders(HorizonServiceProvider::class))
        ->not->toBeEmpty();
});

it('defines production supervisors for each queue', function () {
    $production = config('horizon.environments.production');

    expect($production)->toHaveKeys([
        'payments-supervisor',
        'webhooks-supervisor',
        'notifications-supervisor',
        'compliance-supervisor',
        'default-supervisor',
    ]);

    expect($production['payments-supervisor']['maxProcesses'])->toBe(3);
    expect($production['webhooks-supervisor']['maxProcesses'])->toBe(2);
    expect($production['notifications-supervisor']['maxProcesses'])->toBe(1);
    expect($production['compliance-supervisor']['maxProcesses'])->toBe(1);
    expect($production['default-supervisor']['maxProcesses'])->toBe(2);
});

it('defines local supervisor with all queues', function () {
    $local = config('horizon.environments.local');

    expect($local)->toHaveKey('default-supervisor');
    expect($local['default-supervisor']['maxProcesses'])->toBe(3);
    expect($local['default-supervisor']['queue'])->toHaveCount(5);
});

it('gates horizon dashboard to budera admins', function () {
    $user = User::factory()->create(['is_budera_admin' => true]);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue();
});

it('denies horizon dashboard to non-admin users', function () {
    $user = User::factory()->create(['is_budera_admin' => false]);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});
