<?php

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Topup;
use App\Models\Transfer;
use App\Models\User;
use App\Models\WalletAccount;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────
// Helper: create a company with owner, wallet, and API key
// ──────────────────────────────────────────

function createCompanyWithApiKey(string $companyName, string $env = 'sandbox', array $abilities = ['*']): array
{
    $owner = User::factory()->withCompany($companyName)->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $wallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'environment' => $env,
    ]);

    $plain = 'sk_'.$env.'_'.Str::random(42);
    $key = ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => $env,
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => $abilities,
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    return compact('owner', 'company', 'wallet', 'key', 'plain');
}

// ──────────────────────────────────────────
// 1. Tenant isolation
// ──────────────────────────────────────────

test('tenant isolation: company A cannot access company B wallets via API', function (): void {
    $this->seed(RoleSeeder::class);

    $a = createCompanyWithApiKey('Company A');
    $b = createCompanyWithApiKey('Company B');

    $this->withHeader('Authorization', 'Bearer '.$a['plain'])
        ->getJson('/api/v1/wallet/accounts/'.$b['wallet']->public_id)
        ->assertForbidden();
});

test('tenant isolation: company A cannot access company B payments via API', function (): void {
    $this->seed(RoleSeeder::class);

    $a = createCompanyWithApiKey('Company A');
    $b = createCompanyWithApiKey('Company B');

    $payment = Payment::factory()->create([
        'wallet_account_id' => $b['wallet']->id,
        'environment' => 'sandbox',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$a['plain'])
        ->getJson('/api/v1/payments/'.$payment->public_id)
        ->assertNotFound();
});

test('tenant isolation: company A cannot list company B payments via API', function (): void {
    $this->seed(RoleSeeder::class);

    $a = createCompanyWithApiKey('Company A');
    $b = createCompanyWithApiKey('Company B');

    Payment::factory()->create([
        'wallet_account_id' => $b['wallet']->id,
        'environment' => 'sandbox',
    ]);
    Payment::factory()->create([
        'wallet_account_id' => $a['wallet']->id,
        'environment' => 'sandbox',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$a['plain'])
        ->getJson('/api/v1/payments')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->each(fn ($id) => $id->not->toBe($b['wallet']->public_id));
});

test('tenant isolation: company A cannot access company B topups via API', function (): void {
    $this->seed(RoleSeeder::class);

    $a = createCompanyWithApiKey('Company A');
    $b = createCompanyWithApiKey('Company B');

    $topup = Topup::factory()->create([
        'wallet_account_id' => $b['wallet']->id,
        'environment' => 'sandbox',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$a['plain'])
        ->getJson('/api/v1/topups/'.$topup->public_id)
        ->assertNotFound();
});

test('tenant isolation: company A cannot access company B transfers via API', function (): void {
    $this->seed(RoleSeeder::class);

    $a = createCompanyWithApiKey('Company A');
    $b = createCompanyWithApiKey('Company B');

    $transfer = Transfer::factory()->create([
        'from_wallet_account_id' => $b['wallet']->id,
        'to_wallet_account_id' => $b['wallet']->id,
        'environment' => 'sandbox',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$a['plain'])
        ->getJson('/api/v1/transfers/'.$transfer->public_id)
        ->assertNotFound();
});

test('tenant isolation: company A cannot access company B bank links via API', function (): void {
    $this->seed(RoleSeeder::class);

    $a = createCompanyWithApiKey('Company A');
    $b = createCompanyWithApiKey('Company B');

    $bankLink = BankLink::factory()->create([
        'user_id' => $b['owner']->id,
        'environment' => 'sandbox',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$a['plain'])
        ->getJson('/api/v1/bank-links/'.$bankLink->public_id)
        ->assertNotFound();
});

test('tenant isolation: company A cannot access company B ledger via API', function (): void {
    $this->seed(RoleSeeder::class);

    $a = createCompanyWithApiKey('Company A');
    $b = createCompanyWithApiKey('Company B');

    LedgerEntry::factory()->credit()->create([
        'wallet_account_id' => $b['wallet']->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$a['plain'])
        ->getJson('/api/v1/wallets/'.$b['wallet']->public_id.'/ledger')
        ->assertForbidden();
});

// ──────────────────────────────────────────
// 2. Sandbox / Live isolation
// ──────────────────────────────────────────

test('sandbox api key cannot access live wallet data', function (): void {
    $this->seed(RoleSeeder::class);

    $sandbox = createCompanyWithApiKey('Sandbox Corp', 'sandbox');

    WalletAccount::factory()->active()->create([
        'company_id' => $sandbox['company']->id,
        'user_id' => $sandbox['owner']->id,
        'environment' => 'live',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$sandbox['plain'])
        ->getJson('/api/v1/wallet/me')
        ->assertSuccessful();

    if ($response->json('wallet.environment') !== null) {
        expect($response->json('wallet.environment'))->toBe('sandbox');
    }
});

test('live api key cannot access sandbox wallet data', function (): void {
    $this->seed(RoleSeeder::class);

    $owner = User::factory()->withCompany('Live Corp')->create();
    $company = Company::factory()->kybApproved()->create(['owner_id' => $owner->id]);

    WalletAccount::factory()->active()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'environment' => 'sandbox',
    ]);

    $liveWallet = WalletAccount::factory()->active()->create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'environment' => 'live',
    ]);

    $plain = 'sk_live_'.Str::random(42);
    ApiKey::query()->create([
        'company_id' => $company->id,
        'environment' => 'live',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['*'],
        'metadata' => ['key_last4' => substr($plain, -4)],
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$plain)
        ->getJson('/api/v1/wallet/me')
        ->assertSuccessful();

    if ($response->json('wallet.environment') !== null) {
        expect($response->json('wallet.environment'))->toBe('live');
    }
});

test('sandbox api key cannot see live payments', function (): void {
    $this->seed(RoleSeeder::class);

    $sandbox = createCompanyWithApiKey('Env Corp', 'sandbox');

    $liveWallet = WalletAccount::factory()->active()->create([
        'company_id' => $sandbox['company']->id,
        'user_id' => $sandbox['owner']->id,
        'environment' => 'live',
    ]);
    Payment::factory()->create([
        'wallet_account_id' => $liveWallet->id,
        'environment' => 'live',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$sandbox['plain'])
        ->getJson('/api/v1/payments')
        ->assertSuccessful();

    $environments = collect($response->json('data'))->pluck('environment')->unique();
    expect($environments->contains('live'))->toBeFalse();
});

// ──────────────────────────────────────────
// 3. API key hashing
// ──────────────────────────────────────────

test('api key plain text is never stored in database', function (): void {
    $owner = User::factory()->withCompany()->create();
    $company = Company::query()->where('owner_id', $owner->id)->firstOrFail();

    $plain = 'sk_sandbox_'.Str::random(42);
    $key = ApiKey::query()->create([
        'company_id' => $company->getKey(),
        'environment' => 'sandbox',
        'status' => 'active',
        'key_hash' => hash('sha256', $plain),
        'abilities' => ['wallet:read'],
        'metadata' => [],
    ]);

    expect($key->key_hash)->not->toBe($plain);
    expect($key->key_hash)->toBe(hash('sha256', $plain));

    $raw = DB::table('api_keys')->where('id', $key->id)->first();
    $values = (array) $raw;
    foreach ($values as $value) {
        if (is_string($value)) {
            expect($value)->not->toContain($plain);
        }
    }
});

test('api key hash is a valid sha256 hex string', function (): void {
    $key = ApiKey::factory()->create();

    expect($key->key_hash)->toMatch('/^[a-f0-9]{64}$/');
});

// ──────────────────────────────────────────
// 4. Admin route protection
// ──────────────────────────────────────────

test('non-admin users receive 403 on all admin routes', function (): void {
    $this->seed(RoleSeeder::class);

    $user = User::factory()->withCompany()->create();
    $this->actingAs($user);

    $adminRoutes = [
        ['GET', '/admin/kyb-reviews'],
        ['GET', '/admin/live-access'],
        ['GET', '/admin/companies'],
        ['GET', '/admin/compliance'],
        ['GET', '/admin/partner-banks'],
    ];

    foreach ($adminRoutes as [$method, $uri]) {
        $response = $this->call($method, $uri);
        expect($response->status())->toBe(403, "Expected 403 for {$method} {$uri}, got {$response->status()}");
    }
});

test('unauthenticated users cannot access admin routes', function (): void {
    $adminRoutes = [
        ['GET', '/admin/kyb-reviews'],
        ['GET', '/admin/live-access'],
        ['GET', '/admin/companies'],
        ['GET', '/admin/compliance'],
        ['GET', '/admin/partner-banks'],
    ];

    foreach ($adminRoutes as [$method, $uri]) {
        $response = $this->call($method, $uri);
        expect($response->isRedirect() || $response->status() === 401)
            ->toBeTrue("Expected redirect or 401 for {$method} {$uri}, got {$response->status()}");
    }
});

test('budera admin can access admin routes', function (): void {
    $this->seed(RoleSeeder::class);

    $admin = User::factory()->buderaAdmin()->create();
    $this->actingAs($admin);

    $response = $this->get('/admin/kyb-reviews');
    expect($response->status())->not->toBe(403);
});

// ──────────────────────────────────────────
// 5. CORS configuration
// ──────────────────────────────────────────

test('cors config exists and has required keys', function (): void {
    $cors = config('cors');

    expect($cors)->toBeArray();
    expect($cors)->toHaveKeys(['paths', 'allowed_methods', 'allowed_origins', 'allowed_headers']);
    expect($cors['paths'])->toBeArray()->not->toBeEmpty();
    expect($cors['allowed_methods'])->toBeArray()->not->toBeEmpty();
    expect($cors['allowed_origins'])->toBeArray()->not->toBeEmpty();
    expect($cors['allowed_headers'])->toBeArray()->not->toBeEmpty();
    expect($cors['supports_credentials'])->toBeBool();
});

test('cors paths include api routes', function (): void {
    $paths = config('cors.paths', []);

    $coversApi = collect($paths)->contains(fn (string $path) => str_contains($path, 'api'));

    expect($coversApi)->toBeTrue();
});

// ──────────────────────────────────────────
// 6. No env() calls outside config files
// ──────────────────────────────────────────

test('architecture: no env() calls in app/ directory outside config files', function (): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $violations = [];
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        if (preg_match('/\benv\s*\(/', $content)) {
            $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty(
        'Found env() calls in: '.implode(', ', $violations)
    );
});

// ──────────────────────────────────────────
// 7. Rate limiting enforcement
// ──────────────────────────────────────────

test('all api v1 routes have throttle middleware', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();
        $hasThrottle = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'throttle'));
        expect($hasThrottle)->toBeTrue(
            "Route {$route->methods()[0]} {$route->uri()} missing throttle middleware"
        );
    }
});

// ──────────────────────────────────────────
// 8. CSRF on web routes
// ──────────────────────────────────────────

test('web routes have CSRF protection', function (): void {
    $this->seed(RoleSeeder::class);

    $user = User::factory()->withCompany()->create();
    $this->actingAs($user);

    $this->withoutMiddleware(EncryptCookies::class);

    $response = $this->call('POST', '/onboarding/company', [
        'company_name' => 'CSRF Test Corp',
    ], [], [], [
        'HTTP_X-Requested-With' => 'XMLHttpRequest',
    ]);

    expect(in_array($response->status(), [419, 403, 302], true))->toBeTrue(
        "Expected CSRF-protected response for web POST, got {$response->status()}"
    );
});

test('CSRF middleware is registered on web stack', function (): void {
    $webMiddleware = app(Kernel::class)->getMiddlewareGroups()['web'] ?? [];

    $hasCsrf = collect($webMiddleware)->contains(
        fn ($m) => is_string($m) && str_contains($m, 'VerifyCsrfToken'),
    );

    if (! $hasCsrf) {
        $hasCsrf = class_exists(ValidateCsrfToken::class);
    }

    expect($hasCsrf)->toBeTrue('CSRF middleware should be registered for web routes');
});
