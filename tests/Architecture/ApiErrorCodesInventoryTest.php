<?php

use Symfony\Component\Finder\Finder;

/**
 * Ensures config/api_errors.php stays aligned with ApiErrorResponse usage and documented dynamic codes.
 */
test('api error codes in config match scanned literals plus declared exception and handler codes', function (): void {
    $fixturePath = dirname(__DIR__).'/Fixtures/expected_api_error_codes.php';
    /** @var array{exception_message_codes: list<string>, declared_without_scan_match: list<string>} $fixture */
    $fixture = require $fixturePath;

    /** @var array<string, array{message: string, layer: string, status: int}> $catalog */
    $catalog = config('api_errors.codes');
    $configKeys = array_keys($catalog);
    sort($configKeys);

    $scanned = scanApiErrorResponseCodeLiterals();
    sort($scanned);

    $fromFixture = array_values(array_unique(array_merge(
        $fixture['exception_message_codes'],
        $fixture['declared_without_scan_match'],
    )));
    sort($fromFixture);

    $union = array_values(array_unique(array_merge($scanned, $fromFixture)));
    sort($union);

    expect($union)->toBe($configKeys, 'Every config/api_errors.php code must appear as an ApiErrorResponse string literal in app/Http or bootstrap, or be listed in tests/Fixtures/expected_api_error_codes.php. Update the fixture when adding handler-only codes.');
});

/**
 * @return list<string>
 */
function scanApiErrorResponseCodeLiterals(): array
{
    $finder = Finder::create()
        ->files()
        ->name('*.php')
        ->in(base_path('app/Http'));

    $files = iterator_to_array($finder, false);
    $files[] = new SplFileInfo(base_path('bootstrap/app.php'));
    $files[] = new SplFileInfo(base_path('app/Providers/AppServiceProvider.php'));

    $found = [];

    $patterns = [
        '/ApiErrorResponse::json\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/',
        '/ApiErrorResponse::jsonWith\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/',
    ];

    foreach ($files as $file) {
        $path = $file->getPathname();
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches) !== 0) {
                foreach ($matches[1] as $code) {
                    $found[$code] = true;
                }
            }
        }
    }

    $out = array_keys($found);
    sort($out);

    return $out;
}
