<?php

namespace Tests\Support;

final class MockBankWebhook
{
    public static function signRawBody(string $rawBody, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $rawBody, $secret);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{raw: string, signature: string}
     */
    public static function signPayload(array $payload, string $secret): array
    {
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'raw' => $raw,
            'signature' => self::signRawBody($raw, $secret),
        ];
    }
}
