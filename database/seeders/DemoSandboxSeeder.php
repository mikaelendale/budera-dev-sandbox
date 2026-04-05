<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\BankLink;
use App\Models\Company;
use App\Models\OAuthClient;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\Services\Ledger\LedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoSandboxSeeder extends Seeder
{
    public const DEVELOPER_EMAIL = 'dev@demo-ai.test';

    public const END_USER_EMAIL = 'enduser@demo.test';

    public const PASSWORD = 'password';

    /**
     * @return array{developer: User, company: Company, api_key_plain: string, end_user: User, wallet: WalletAccount, bank_link: BankLink, oauth_client: OAuthClient, oauth_client_secret: string}
     */
    public function run(): array
    {
        $this->call(RoleSeeder::class);

        $developer = User::query()->where('email', self::DEVELOPER_EMAIL)->first();
        if ($developer === null) {
            $developer = User::factory()->create([
                'name' => 'Demo Developer',
                'email' => self::DEVELOPER_EMAIL,
                'user_type' => 'developer',
                'password' => bcrypt(self::PASSWORD),
            ]);
        }

        $company = $developer->firstCompany();
        if ($company === null) {
            $company = Company::query()->create([
                'name' => 'Demo AI Company',
                'email' => self::DEVELOPER_EMAIL,
                'owner_id' => $developer->getKey(),
                'kyb_status' => 'approved',
            ]);

            setPermissionsTeamId($company->getKey());
            $developer->assignRole(Role::findOrCreate('company_owner', 'web'));
            setPermissionsTeamId(null);
        }

        $apiKeyPlain = 'sk_sandbox_demo_'.Str::random(32);
        $apiKey = ApiKey::query()->updateOrCreate(
            ['company_id' => $company->getKey(), 'label' => 'demo-all-access'],
            [
                'environment' => 'sandbox',
                'status' => 'active',
                'key_hash' => hash('sha256', $apiKeyPlain),
                'abilities' => [
                    'wallet:read',
                    'wallet:pay',
                    'wallet:topup',
                    'wallet:link',
                    'wallet:transfer',
                    'wallet:approve',
                    'sandbox:simulate',
                ],
                'metadata' => ['key_last4' => substr($apiKeyPlain, -4)],
            ],
        );

        $endUser = User::query()->where('email', self::END_USER_EMAIL)->first();
        if ($endUser === null) {
            $endUser = User::factory()->create([
                'name' => 'Demo End User',
                'email' => self::END_USER_EMAIL,
                'user_type' => 'end_user',
                'password' => bcrypt(self::PASSWORD),
            ]);
        }

        setPermissionsTeamId($company->getKey());
        if (! $endUser->hasRole('end_user')) {
            $endUser->assignRole(Role::findOrCreate('end_user', 'web'));
        }
        setPermissionsTeamId(null);

        $wallet = WalletAccount::query()
            ->withoutGlobalScopes()
            ->where('user_id', $endUser->getKey())
            ->whereNull('company_id')
            ->first();

        if ($wallet === null) {
            $wallet = WalletAccount::query()->create([
                'company_id' => null,
                'user_id' => $endUser->getKey(),
                'environment' => 'sandbox',
                'status' => 'active',
                'partner_account_id' => 'mock_acct_'.bin2hex(random_bytes(8)),
                'balance_cents' => 0,
                'metadata' => ['provisioned_by' => 'demo_seeder'],
            ]);

            WalletKycVerification::query()->create([
                'wallet_account_id' => $wallet->getKey(),
                'status' => 'approved',
                'session_token' => Str::random(48),
                'verified_at' => now(),
            ]);
        }
        $this->seedOpeningCreditIfMissing(
            $wallet,
            10_000,
            'Demo end-user opening credit',
        );

        $bankLink = BankLink::query()
            ->withoutGlobalScopes()
            ->where('user_id', $endUser->getKey())
            ->where('wallet_account_id', $wallet->getKey())
            ->first();

        if ($bankLink === null) {
            $bankLink = BankLink::factory()->mockBank()->create([
                'user_id' => $endUser->getKey(),
                'wallet_account_id' => $wallet->getKey(),
                'company_id' => $company->getKey(),
                'environment' => 'sandbox',
            ]);
        }

        $companyWallet = WalletAccount::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->first();

        if ($companyWallet === null) {
            $companyWallet = WalletAccount::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $developer->getKey(),
                'environment' => 'sandbox',
                'status' => 'active',
                'partner_account_id' => 'mock_acct_'.bin2hex(random_bytes(8)),
                'balance_cents' => 0,
                'metadata' => ['provisioned_by' => 'demo_seeder'],
            ]);
        }
        $this->seedOpeningCreditIfMissing(
            $companyWallet,
            50_000,
            'Demo company opening credit',
        );

        $oauthSecret = Str::random(40);
        $oauthClient = OAuthClient::query()
            ->where('name', 'Demo AI Agent')
            ->where('revoked', false)
            ->first();

        if ($oauthClient === null) {
            $oauthClient = OAuthClient::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'Demo AI Agent',
                'secret' => $oauthSecret,
                'provider' => 'users',
                'redirect_uris' => ['http://localhost:3000/oauth/callback'],
                'grant_types' => ['authorization_code'],
                'revoked' => false,
                'company_id' => $company->getKey(),
            ]);
        } else {
            $oauthSecret = '(existing — check your database or recreate)';
        }

        return [
            'developer' => $developer,
            'company' => $company,
            'api_key_plain' => $apiKeyPlain,
            'end_user' => $endUser,
            'wallet' => $wallet,
            'bank_link' => $bankLink,
            'company_wallet' => $companyWallet,
            'oauth_client' => $oauthClient,
            'oauth_client_secret' => $oauthSecret,
        ];
    }

    private function seedOpeningCreditIfMissing(
        WalletAccount $wallet,
        int $defaultOpeningBalanceCents,
        string $description,
    ): void {
        if ($wallet->ledgerEntries()->exists()) {
            return;
        }

        $existingBalance = (int) $wallet->balance_cents;
        $openingAmount = $existingBalance > 0
            ? $existingBalance
            : $defaultOpeningBalanceCents;

        if ($openingAmount <= 0) {
            return;
        }

        app(LedgerService::class)->credit(
            $wallet->fresh(),
            $openingAmount,
            'manual_credit',
            'seed_'.$wallet->public_id,
            $description,
            ['source' => 'demo_seeder'],
        );
    }
}
