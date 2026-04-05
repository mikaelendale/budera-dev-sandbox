<?php

use Illuminate\Support\Facades\Artisan;

test('every api v1 route uses auth throttle and api group', function (): void {
    Artisan::call('route:list', [
        '--path' => 'api/v1',
        '--json' => true,
    ]);

    $raw = Artisan::output();
    /** @var list<array{uri: string, middleware: list<string>}> $routes */
    $routes = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $middleware = $route['middleware'] ?? [];
        expect($middleware)->toContain('api')
            ->and($middleware)->toContain('auth:api-key,api')
            ->and($middleware)->toContain('throttle:api-company');
    }
});
