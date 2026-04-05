<?php

use App\Models\User;

test('budera:token prints a personal access token for a user', function (): void {
    $user = User::factory()->create(['email' => 'budera-cli-test@example.test']);

    $this->artisan('budera:token', [
        'email' => $user->email,
        '--scopes' => 'wallet:read',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Personal access token');
});
