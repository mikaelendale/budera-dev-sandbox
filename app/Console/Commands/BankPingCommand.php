<?php

namespace App\Console\Commands;

use App\Services\Banking\MockBankClient;
use App\Services\Banking\PartnerBankIntegrationResolver;
use Illuminate\Console\Command;

class BankPingCommand extends Command
{
    protected $signature = 'bank:ping';

    protected $description = 'Ping the Column mock bank GET /health endpoint';

    public function handle(MockBankClient $client, PartnerBankIntegrationResolver $resolver): int
    {
        $base = $resolver->resolveForProvider('column-mock-inline')['base_url'];
        if ($base === null || $base === '') {
            $this->error('Mock bank base URL is not set (partner_bank_integrations missing).');

            return self::FAILURE;
        }

        try {
            $data = $client->health();
            $this->info('OK: '.json_encode($data));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
