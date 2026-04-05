<?php

namespace App\Services\Banking;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MockBankClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $secret,
    ) {}

    public static function fromResolver(PartnerBankIntegrationResolver $resolver): self
    {
        $cfg = $resolver->resolveForProvider('mock_bank');

        return new self(
            rtrim((string) ($cfg['base_url'] ?? ''), '/'),
            $cfg['outbound_secret'],
        );
    }

    public static function fromConfig(): self
    {
        $config = config('services.mock_bank', []);

        return new self(
            rtrim((string) ($config['base_url'] ?? ''), '/'),
            isset($config['secret']) && $config['secret'] !== '' ? (string) $config['secret'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        if ($this->shouldUseInlineResponses()) {
            return ['ok' => true, 'service' => 'column-mock-inline'];
        }

        return $this->json('GET', '/health');
    }

    /**
     * @return array<string, mixed>
     */
    public function createAccount(string $currency = 'USD'): array
    {
        if ($this->shouldUseInlineResponses()) {
            return [
                'id' => 'acct_'.Str::lower(Str::random(24)),
                'currency' => $currency,
                'created_at' => now()->utc()->toIso8601String(),
            ];
        }

        return $this->json('POST', '/api/accounts', ['currency' => $currency]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBalance(string $accountId): array
    {
        if ($this->shouldUseInlineResponses()) {
            return [
                'balance_cents' => 0,
                'currency' => 'USD',
            ];
        }

        return $this->json('GET', "/api/accounts/{$accountId}/balance");
    }

    /**
     * @return array<string, mixed>
     */
    public function achPush(string $accountId, int $amountCents, ?string $idempotencyKey = null): array
    {
        return $this->transferAch('credit', $accountId, $amountCents, $idempotencyKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function achPull(string $accountId, int $amountCents, ?string $idempotencyKey = null): array
    {
        return $this->transferAch('debit', $accountId, $amountCents, $idempotencyKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function transferAch(
        string $direction,
        string $accountId,
        int $amountCents,
        ?string $idempotencyKey = null,
    ): array {
        if ($this->shouldUseInlineResponses()) {
            $id = 'trf_'.Str::lower(Str::random(16));

            return [
                'transfer_id' => $id,
                'ref' => $id,
                'rail' => 'ach',
                'status' => 'pending',
                'duplicate' => false,
            ];
        }

        $body = [
            'direction' => $direction,
            'account_id' => $accountId,
            'amount_cents' => $amountCents,
        ];
        if ($idempotencyKey !== null) {
            $body['idempotency_key'] = $idempotencyKey;
        }

        return $this->json('POST', '/api/transfers/ach', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function transferWire(array $body): array
    {
        if ($this->shouldUseInlineResponses()) {
            return $this->inlineTransferResponse('wire');
        }

        return $this->json('POST', '/api/transfers/wire', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function transferSwift(array $body): array
    {
        if ($this->shouldUseInlineResponses()) {
            return $this->inlineTransferResponse('swift');
        }

        return $this->json('POST', '/api/transfers/swift', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function transferFednow(array $body): array
    {
        if ($this->shouldUseInlineResponses()) {
            return $this->inlineTransferResponse('fednow');
        }

        return $this->json('POST', '/api/transfers/fednow', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function transferRealtime(array $body): array
    {
        if ($this->shouldUseInlineResponses()) {
            return $this->inlineTransferResponse('realtime');
        }

        return $this->json('POST', '/api/transfers/realtime', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function transferBook(array $body): array
    {
        if ($this->shouldUseInlineResponses()) {
            return $this->inlineTransferResponse('book');
        }

        return $this->json('POST', '/api/transfers/book', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function transferCheck(array $body): array
    {
        if ($this->shouldUseInlineResponses()) {
            return $this->inlineTransferResponse('check');
        }

        return $this->json('POST', '/api/transfers/check', $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransfer(string $id): array
    {
        if ($this->shouldUseInlineResponses()) {
            return [
                'id' => $id,
                'rail' => 'ach',
                'status' => 'settled',
                'amount_cents' => 0,
            ];
        }

        return $this->json('GET', "/api/transfers/{$id}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransaction(string $ref): array
    {
        return $this->getTransfer($ref);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function submitKycSubmission(array $body): array
    {
        if ($this->shouldUseInlineResponses()) {
            return [
                'id' => 'kyc_'.Str::lower(Str::random(24)),
                'status' => 'pending',
                'created_at' => now()->utc()->toIso8601String(),
            ];
        }

        return $this->json('POST', '/api/kyc/submissions', $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function getKycSubmission(string $id): array
    {
        if ($this->shouldUseInlineResponses()) {
            return [
                'id' => $id,
                'status' => 'approved',
                'created_at' => now()->utc()->toIso8601String(),
                'resolved_at' => now()->utc()->toIso8601String(),
                'account_id' => null,
                'payload' => [],
            ];
        }

        return $this->json('GET', "/api/kyc/submissions/{$id}");
    }

    /**
     * @return array<string, mixed>
     */
    public function settleNow(string $ref): array
    {
        if ($this->shouldUseInlineResponses()) {
            return ['ok' => true, 'ref' => $ref];
        }

        return $this->postControl('/api/control/settle-now', ['ref' => $ref]);
    }

    /**
     * @return array<string, mixed>
     */
    public function achReturn(string $ref): array
    {
        if ($this->shouldUseInlineResponses()) {
            return ['ok' => true, 'ref' => $ref];
        }

        return $this->postControl('/api/control/ach-return', ['ref' => $ref]);
    }

    /**
     * @return array<string, mixed>
     */
    private function inlineTransferResponse(string $rail): array
    {
        $id = 'trf_'.Str::lower(Str::random(16));

        return [
            'transfer_id' => $id,
            'ref' => $id,
            'rail' => $rail,
            'status' => 'pending',
            'duplicate' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postControl(string $path, array $body): array
    {
        $url = $this->baseUrl.$path;
        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->asJson()
            ->post($url, $body);

        if (in_array($response->status(), [404, 422], true)) {
            /** @var array<string, mixed>|null $json */
            $json = $response->json();
            $error = is_array($json) && isset($json['error']) ? (string) $json['error'] : 'mock_bank_control_failed';

            throw new InvalidArgumentException($error);
        }

        $response->throw();

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, array $body = []): array
    {
        $url = $this->baseUrl.$path;
        $request = Http::withHeaders($this->headers())->acceptJson();

        $response = match ($method) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $body),
            default => throw new InvalidArgumentException("Unsupported method: {$method}"),
        };

        $response->throw();

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [];
        if ($this->secret !== null && $this->secret !== '') {
            $headers['X-Bank-Secret'] = $this->secret;
        }

        return $headers;
    }

    private function shouldUseInlineResponses(): bool
    {
        return (bool) config('services.mock_bank.inline', false);
    }
}
