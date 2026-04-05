<?php

namespace App\Services\Banking;

use App\Models\PartnerBankIntegration;

class PartnerBankIntegrationResolver
{
    public function defaultEnvironment(): string
    {
        return app()->isProduction() ? 'live' : 'sandbox';
    }

    /**
     * Resolve outbound URL and secrets for a provider (e.g. mock_bank, column).
     * This is DB-first. During tests (APP_ENV=testing) we allow a config fallback for
     * existing MockBankClient/unit tests.
     *
     * @return array{base_url: string, outbound_secret: ?string, inbound_webhook_secret: ?string}
     */
    public function resolveForProvider(string $provider): array
    {
        $env = $this->defaultEnvironment();

        $row = PartnerBankIntegration::query()
            ->where('provider', $provider)
            ->where('environment', $env)
            ->where('is_active', true)
            ->first();

        if ($row !== null) {
            $c = is_array($row->credentials) ? $row->credentials : [];

            return [
                'base_url' => rtrim((string) ($row->base_url ?? ''), '/'),
                'outbound_secret' => $this->nonEmptyString($c['outbound_api_secret'] ?? null),
                'inbound_webhook_secret' => $this->nonEmptyString($c['inbound_webhook_secret'] ?? null),
            ];
        }

        return $this->fallbackConfig($provider);
    }

    /**
     * @return array{base_url: string, outbound_secret: ?string, inbound_webhook_secret: ?string}
     */
    private function fallbackConfig(string $provider): array
    {
        if ($provider === 'mock_bank' && app()->environment('testing')) {
            $config = config('services.mock_bank', []);

            return [
                'base_url' => rtrim((string) ($config['base_url'] ?? ''), '/'),
                'outbound_secret' => $this->nonEmptyString($config['secret'] ?? null),
                'inbound_webhook_secret' => $this->nonEmptyString($config['webhook_secret'] ?? null),
            ];
        }

        return [
            'base_url' => '',
            'outbound_secret' => null,
            'inbound_webhook_secret' => null,
        ];
    }

    private function nonEmptyString(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return $v;
    }

    /**
     * Live Column HTTP client is only used when production and an active column+live integration exists with outbound credentials.
     */
    public function useLiveColumnClient(): bool
    {
        if (! app()->isProduction()) {
            return false;
        }

        $row = PartnerBankIntegration::query()
            ->where('provider', 'column')
            ->where('environment', 'live')
            ->where('is_active', true)
            ->first();

        if ($row === null) {
            return false;
        }

        $c = is_array($row->credentials) ? $row->credentials : [];
        $out = $c['outbound_api_secret'] ?? '';

        return is_string($out) && $out !== '';
    }
}
