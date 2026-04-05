<?php

namespace Database\Seeders;

use App\Models\PartnerBankIntegration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class BuderaAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('BUDERA_ADMIN_EMAIL', 'budera-admin@local.test');

        if (! User::query()->where('email', $email)->exists()) {
            User::factory()
                ->buderaAdmin()
                ->create([
                    'name' => env('BUDERA_ADMIN_NAME', 'Budera Admin'),
                    'email' => $email,
                ]);
        }

        $admin = User::query()->where('email', $email)->first();

        if ($admin !== null) {
            // Team 0 is reserved for global/internal Budera roles.
            setPermissionsTeamId(0);
            $admin->assignRole(Role::findOrCreate('budera_admin', 'web'));
            setPermissionsTeamId(null);
        }

        // Seed the default mock bank integration so the main app can run without
        // relying on `.env` fallback for runtime partner-bank config.
        $environment = app()->isProduction() ? 'live' : 'sandbox';
        $mockBaseUrl = env('MOCK_BANK_BASE_URL', '');
        $mockOutboundSecret = env('MOCK_BANK_SECRET', '');
        $mockInboundWebhookSecret = env('MOCK_BANK_WEBHOOK_SECRET', '');

        if ($mockBaseUrl !== '' && $mockOutboundSecret !== '') {
            PartnerBankIntegration::query()->updateOrCreate(
                [
                    'provider' => 'mock_bank',
                    'environment' => $environment,
                ],
                [
                    'label' => 'Mock bank (local)',
                    'base_url' => rtrim($mockBaseUrl, '/'),
                    'credentials' => [
                        'outbound_api_secret' => $mockOutboundSecret,
                        'inbound_webhook_secret' => $mockInboundWebhookSecret,
                    ],
                    'is_active' => true,
                ],
            );
        }
    }
}
