<?php

use Inertia\Testing\AssertableInertia as Assert;

test('docs index redirects to overview', function (): void {
    $this->get('/docs')->assertRedirect(route('docs.show', ['page' => 'overview']));
});

test('docs overview page renders inertia docs', function (): void {
    $this->get('/docs/overview')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Docs/Show')
            ->has('title')
            ->has('html')
            ->where('page', 'overview'));
});

test('docs rejects unknown slug', function (): void {
    $this->get('/docs/not-a-real-page')->assertNotFound();
});
