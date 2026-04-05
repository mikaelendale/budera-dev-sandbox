<?php

test('mock bank webhook returns webhook_not_configured when inbound secret is empty', function (): void {
    config(['services.mock_bank.webhook_secret' => '']);

    $raw = json_encode(['event' => 'kyc.verified', 'data' => ['kyc_submission_id' => 'x']], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, 'ignored'),
    ], $raw)
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'webhook_not_configured');
});

test('mock bank webhook returns invalid_signature when signature header is wrong', function (): void {
    config(['services.mock_bank.webhook_secret' => 'whsec_sig_test']);

    $raw = json_encode(['event' => 'ping', 'data' => []], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/webhooks/mock-bank', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => 'sha256=deadbeef',
    ], $raw)
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_signature');
});
