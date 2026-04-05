<?php

namespace App\Console\Commands;

use App\Models\OAuthClient;
use Database\Seeders\DemoSandboxSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DemoCommand extends Command
{
    protected $signature = 'budera:demo
                            {--fresh : Wipe and re-migrate before seeding}
                            {--dry : Only seed, print curl commands, do not auto-execute}';

    protected $description = 'Seed a demo AI company + end user, then execute the full API flow end-to-end';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command only runs in local/testing environments.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $this->info('Seeding demo sandbox data...');

        $seeder = new DemoSandboxSeeder;
        $seeder->setCommand($this);
        $result = $seeder->run();

        $base = rtrim((string) config('app.url'), '/');
        $apiKey = $result['api_key_plain'];
        $wallet = $result['wallet'];
        $companyWallet = $result['company_wallet'];
        $bankLink = $result['bank_link'];
        $endUser = $result['end_user'];
        $developer = $result['developer'];
        $company = $result['company'];
        $oauthClient = $result['oauth_client'];
        $oauthSecret = $result['oauth_client_secret'];

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>DEMO SANDBOX READY</>');
        $this->newLine();

        $this->components->twoColumnDetail('Base URL', $base);
        $this->components->twoColumnDetail('Developer login', DemoSandboxSeeder::DEVELOPER_EMAIL.' / '.DemoSandboxSeeder::PASSWORD);
        $this->components->twoColumnDetail('End user login', DemoSandboxSeeder::END_USER_EMAIL.' / '.DemoSandboxSeeder::PASSWORD);
        $this->components->twoColumnDetail('Company', $company->name.' (id: '.$company->getKey().')');
        $this->components->twoColumnDetail('API Key', $apiKey);
        $this->components->twoColumnDetail('Company wallet', $companyWallet->public_id.' (balance: $'.number_format($companyWallet->balance_cents / 100, 2).')');
        $this->components->twoColumnDetail('End-user wallet', $wallet->public_id.' (balance: $'.number_format($wallet->balance_cents / 100, 2).')');
        $this->components->twoColumnDetail('Bank link', $bankLink->public_id.' (status: '.$bankLink->status->getValue().')');
        $this->components->twoColumnDetail('OAuth client_id', $oauthClient->id);
        $this->components->twoColumnDetail('OAuth redirect_uri', 'http://localhost:3000/oauth/callback');

        if ($this->option('dry')) {
            $this->printCurlGuide($base, $apiKey, $companyWallet, $wallet, $bankLink, $oauthClient, $oauthSecret, $endUser);

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════════');
        $this->components->info('EXECUTING FULL API FLOW');
        $this->line('═══════════════════════════════════════════════════════════');

        $headers = [
            'Authorization' => 'Bearer '.$apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $this->step('1. Read wallet context (GET /api/v1/wallet/me)', function () use ($base, $headers) {
            return Http::withHeaders($headers)->get($base.'/api/v1/wallet/me');
        });

        $this->step('2. Get company wallet (GET /api/v1/wallet/accounts/'.$companyWallet->public_id.')', function () use ($base, $headers, $companyWallet) {
            return Http::withHeaders($headers)->get($base.'/api/v1/wallet/accounts/'.$companyWallet->public_id);
        });

        $this->step('3. Get wallet ledger (GET /api/v1/wallets/'.$companyWallet->public_id.'/ledger)', function () use ($base, $headers, $companyWallet) {
            return Http::withHeaders($headers)->get($base.'/api/v1/wallets/'.$companyWallet->public_id.'/ledger');
        });

        $this->step('4. Fund wallet (+$100)', function () use ($companyWallet) {
            $this->call('budera:credit-wallet', [
                'public_id' => $companyWallet->public_id,
                'amount_cents' => 10000,
            ]);
            $companyWallet->refresh();
            $this->line('   Balance: $'.number_format($companyWallet->balance_cents / 100, 2));

            return null;
        });

        $paymentId = null;
        $this->step('5. Create outbound payment $10 (POST /api/v1/payments)', function () use ($base, $headers, $companyWallet, &$paymentId) {
            $resp = Http::withHeaders(array_merge($headers, ['Idempotency-Key' => 'demo-pay-'.time()]))->post($base.'/api/v1/payments', [
                'wallet_account_id' => $companyWallet->public_id,
                'amount_cents' => 1000,
                'payee_ref' => 'vendor-pizza',
                'category' => 'food',
            ]);

            $data = $resp->json('data') ?? $resp->json();
            $paymentId = $data['id'] ?? null;

            return $resp;
        });

        if ($paymentId !== null) {
            $this->step('6. Settle payment (POST /api/v1/sandbox/simulate/settlement)', function () use ($base, $headers, $paymentId) {
                return Http::withHeaders($headers)->post($base.'/api/v1/sandbox/simulate/settlement', [
                    'payment_id' => $paymentId,
                ]);
            });

            $this->step('7. ACH return payment (POST /api/v1/sandbox/simulate/return)', function () use ($base, $headers, $paymentId) {
                return Http::withHeaders($headers)->post($base.'/api/v1/sandbox/simulate/return', [
                    'payment_id' => $paymentId,
                ]);
            });
        }

        $newBankLinkId = null;
        $this->step('8. Create bank link (POST /api/v1/bank-links)', function () use ($base, $headers, &$newBankLinkId) {
            $resp = Http::withHeaders($headers)->post($base.'/api/v1/bank-links', [
                'routing_number' => '021000021',
                'account_number' => '9876543210',
                'bank_slug' => 'mock',
                'environment' => 'sandbox',
            ]);

            $data = $resp->json('data') ?? $resp->json();
            $newBankLinkId = $data['id'] ?? null;

            return $resp;
        });

        if ($newBankLinkId !== null) {
            $this->step('9. Reveal micro-deposit amounts (POST /api/v1/sandbox/simulate/microdeposit)', function () use ($base, $headers, $newBankLinkId) {
                return Http::withHeaders($headers)->post($base.'/api/v1/sandbox/simulate/microdeposit', [
                    'bank_link_id' => $newBankLinkId,
                ]);
            });

            $this->step('10. Verify micro-deposits (POST /api/v1/bank-links/'.$newBankLinkId.'/verify)', function () use ($base, $headers, $newBankLinkId) {
                return Http::withHeaders($headers)->post($base.'/api/v1/bank-links/'.$newBankLinkId.'/verify', [
                    'amount_first_cents' => 12,
                    'amount_second_cents' => 34,
                ]);
            });

            $topupId = null;
            $this->step('11. ACH top-up $50 via bank link (POST /api/v1/topups)', function () use ($base, $headers, $companyWallet, $newBankLinkId, &$topupId) {
                $resp = Http::withHeaders(array_merge($headers, ['Idempotency-Key' => 'demo-topup-'.time()]))->post($base.'/api/v1/topups', [
                    'wallet_account_id' => $companyWallet->public_id,
                    'bank_link_id' => $newBankLinkId,
                    'amount_cents' => 5000,
                ]);

                $data = $resp->json('data') ?? $resp->json();
                $topupId = $data['id'] ?? null;

                return $resp;
            });

            if ($topupId !== null) {
                $this->step('12. Settle top-up (POST /api/v1/sandbox/simulate/settlement)', function () use ($base, $headers, $topupId) {
                    return Http::withHeaders($headers)->post($base.'/api/v1/sandbox/simulate/settlement', [
                        'topup_id' => $topupId,
                    ]);
                });
            }
        }

        $this->step('13. Submit KYC for company wallet (POST /api/v1/wallet/accounts/'.$companyWallet->public_id.'/kyc)', function () use ($base, $headers, $companyWallet) {
            return Http::withHeaders($headers)->post($base.'/api/v1/wallet/accounts/'.$companyWallet->public_id.'/kyc', [
                'legal_name' => 'Demo Agent',
                'date_of_birth' => '1990-01-15',
                'address_line1' => '1 Main St',
                'last4_ssn' => '1234',
            ]);
        });

        $this->step('14. List payments (GET /api/v1/payments)', function () use ($base, $headers) {
            return Http::withHeaders($headers)->get($base.'/api/v1/payments');
        });

        $this->step('15. List topups (GET /api/v1/topups)', function () use ($base, $headers) {
            return Http::withHeaders($headers)->get($base.'/api/v1/topups');
        });

        $this->step('16. OAuth: mint token + access end-user wallet', function () use ($base, $endUser) {
            $this->ensurePersonalAccessClientExists();
            $oauthToken = $endUser->createToken('budera-demo', ['wallet:read', 'wallet:pay'])->accessToken;
            $this->line('   Token: '.substr($oauthToken, 0, 40).'...');

            $resp = Http::withHeaders([
                'Authorization' => 'Bearer '.$oauthToken,
                'Accept' => 'application/json',
            ])->get($base.'/api/v1/wallet/me');

            $this->newLine();
            $this->line('   <fg=white;options=bold>Use this to test OAuth manually:</>');
            $this->line('   <fg=yellow># Bash / Git Bash:</>');
            $this->line('   <fg=cyan>OAUTH_TOKEN=$(php artisan budera:token '.$endUser->email.' --scopes=wallet:read,wallet:pay --plain)</>');
            $this->line('   <fg=cyan>curl -sS "$BASE/api/v1/wallet/me" -H "Authorization: Bearer $OAUTH_TOKEN" -H "Accept: application/json" | json_pp</>');

            return $resp;
        });

        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════════');
        $this->components->info('MANUAL CURL COMMANDS (for reference)');
        $this->line('═══════════════════════════════════════════════════════════');
        $this->printCurlGuide($base, $apiKey, $companyWallet, $wallet, $bankLink, $oauthClient, $oauthSecret, $endUser);

        return self::SUCCESS;
    }

    /**
     * @param  callable(): (Response|null)  $fn
     */
    private function step(string $label, callable $fn): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>▶ '.$label.'</>');

        $resp = $fn();

        if ($resp === null) {
            return;
        }

        $status = $resp->status();
        $statusColor = $status >= 200 && $status < 300 ? 'green' : ($status >= 400 ? 'red' : 'yellow');
        $this->line('   <fg='.$statusColor.'>HTTP '.$status.'</>');

        $json = $resp->json();
        if (is_array($json)) {
            $this->line('   '.json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    private function printCurlGuide(
        string $base,
        string $apiKey,
        mixed $companyWallet,
        mixed $wallet,
        mixed $bankLink,
        mixed $oauthClient,
        string $oauthSecret,
        mixed $endUser,
    ): void {
        $this->newLine();
        $this->printSection('SETUP: set these variables', <<<BASH
        export BASE="{$base}"
        export TOKEN="{$apiKey}"
        BASH);

        $this->newLine();
        $this->printCurl('Get wallet context', <<<'BASH'
        curl -sS "$BASE/api/v1/wallet/me" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" | json_pp
        BASH);

        $this->printCurl('Get company wallet', <<<BASH
        curl -sS "\$BASE/api/v1/wallet/accounts/{$companyWallet->public_id}" -H "Authorization: Bearer \$TOKEN" -H "Accept: application/json" | json_pp
        BASH);

        $this->printCurl('Get ledger', <<<BASH
        curl -sS "\$BASE/api/v1/wallets/{$companyWallet->public_id}/ledger" -H "Authorization: Bearer \$TOKEN" -H "Accept: application/json" | json_pp
        BASH);

        $this->printCurl('Create payment $10', <<<BASH
        curl -sS -X POST "\$BASE/api/v1/payments" -H "Authorization: Bearer \$TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: pay-\$(date +%s)" -d '{"wallet_account_id":"{$companyWallet->public_id}","amount_cents":1000,"payee_ref":"vendor-pizza","category":"food"}' | json_pp
        BASH);

        $this->printCurl('Settle payment (use payment_id from above)', <<<'BASH'
        curl -sS -X POST "$BASE/api/v1/sandbox/simulate/settlement" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"payment_id":"pay_REPLACE_ME"}' | json_pp
        BASH);

        $this->printCurl('Create bank link', <<<'BASH'
        curl -sS -X POST "$BASE/api/v1/bank-links" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"routing_number":"021000021","account_number":"1234567890","bank_slug":"mock","environment":"sandbox"}' | json_pp
        BASH);

        $this->printCurl('Reveal micro-deposits (use bl_id from above)', <<<'BASH'
        curl -sS -X POST "$BASE/api/v1/sandbox/simulate/microdeposit" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"bank_link_id":"bl_REPLACE_ME"}' | json_pp
        BASH);

        $this->printCurl('Verify micro-deposits', <<<'BASH'
        curl -sS -X POST "$BASE/api/v1/bank-links/bl_REPLACE_ME/verify" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"amount_first_cents":12,"amount_second_cents":34}' | json_pp
        BASH);

        $this->printCurl('Top-up $50 (after verifying bank link)', <<<BASH
        curl -sS -X POST "\$BASE/api/v1/topups" -H "Authorization: Bearer \$TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: topup-\$(date +%s)" -d '{"wallet_account_id":"{$companyWallet->public_id}","bank_link_id":"bl_REPLACE_ME","amount_cents":5000}' | json_pp
        BASH);

        $this->printCurl('Settle topup (use topup_id from above)', <<<'BASH'
        curl -sS -X POST "$BASE/api/v1/sandbox/simulate/settlement" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -d '{"topup_id":"top_REPLACE_ME"}' | json_pp
        BASH);

        $this->newLine();
        $this->printSection('OAuth shortcut', "php artisan budera:token {$endUser->email} --scopes=wallet:read,wallet:pay");

        $this->newLine();
        $this->line('<fg=white;options=bold># Use OAuth token to access end-user wallet</>');
        $this->line('<fg=yellow>curl -sS "$BASE/api/v1/wallet/me" -H "Authorization: Bearer PASTE_OAUTH_TOKEN" -H "Accept: application/json" | json_pp</>');
    }

    private function printSection(string $title, string $content): void
    {
        $this->components->info($title);
        $this->line('<fg=yellow>'.trim($content).'</>');
    }

    private function printCurl(string $label, string $command): void
    {
        $this->newLine();
        $this->line('<fg=white;options=bold># '.$label.'</>');
        $this->line('<fg=yellow>'.trim($command).'</>');
    }

    private function ensurePersonalAccessClientExists(): void
    {
        $hasPersonal = OAuthClient::query()
            ->where('revoked', false)
            ->get()
            ->contains(fn (OAuthClient $c): bool => $c->hasGrantType('personal_access'));

        if ($hasPersonal) {
            return;
        }

        OAuthClient::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Personal Access Client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);
    }
}
