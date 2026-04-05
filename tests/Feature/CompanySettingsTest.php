<?php

use App\Models\User;

test('company member can visit company settings', function () {
    $user = User::factory()->withCompany('Acme')->create();

    $this->actingAs($user)
        ->get(route('company.settings'))
        ->assertOk();
});

test('user without a company is redirected from company settings', function () {
    $user = User::factory()->buderaAdmin()->create();

    $this->actingAs($user)
        ->get(route('company.settings'))
        ->assertRedirect(route('dashboard'));
});

test('company member can visit company team page', function () {
    $user = User::factory()->withCompany('Acme')->create();

    $this->actingAs($user)
        ->get(route('company.team'))
        ->assertOk();
});

test('user without a company is redirected from company team page', function () {
    $user = User::factory()->buderaAdmin()->create();

    $this->actingAs($user)
        ->get(route('company.team'))
        ->assertRedirect(route('dashboard'));
});
