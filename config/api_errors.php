<?php

/**
 * Canonical API error codes for JSON responses: { "error": { "code", "message", "detail", "layer" } }.
 * Keys must match the `code` field; values drive default HTTP status and copy.
 *
 * @var array<string, array{message: string, layer: string, status: int}>
 */
return [
    'codes' => [
        'IDEMPOTENCY_KEY_CONFLICT' => [
            'message' => 'This idempotency key was already used with a different request payload.',
            'layer' => 'idempotency',
            'status' => 409,
        ],
        'IDEMPOTENCY_KEY_INVALID' => [
            'message' => 'The Idempotency-Key header is invalid.',
            'layer' => 'idempotency',
            'status' => 400,
        ],
        'IDEMPOTENCY_KEY_REQUIRED' => [
            'message' => 'The Idempotency-Key header is required for this request.',
            'layer' => 'idempotency',
            'status' => 400,
        ],
        'approval_action_failed' => [
            'message' => 'The approval action could not be completed.',
            'layer' => 'policy',
            'status' => 422,
        ],
        'approval_action_forbidden' => [
            'message' => 'You are not allowed to act on this approval.',
            'layer' => 'auth',
            'status' => 403,
        ],
        'bank_link_cannot_revoke' => [
            'message' => 'This bank link cannot be revoked in its current state.',
            'layer' => 'policy',
            'status' => 422,
        ],
        'bank_link_not_awaiting_microdeposit' => [
            'message' => 'This bank link is not awaiting micro-deposit verification.',
            'layer' => 'policy',
            'status' => 422,
        ],
        'bank_link_not_awaiting_verification' => [
            'message' => 'This bank link is not awaiting verification.',
            'layer' => 'policy',
            'status' => 422,
        ],
        'company_context_required' => [
            'message' => 'A resolved company context is required.',
            'layer' => 'auth',
            'status' => 403,
        ],
        'company_required' => [
            'message' => 'An active company membership is required.',
            'layer' => 'auth',
            'status' => 403,
        ],
        'end_user_not_found' => [
            'message' => 'No end user matched the provided identifier.',
            'layer' => 'validation',
            'status' => 422,
        ],
        'end_user_not_in_company' => [
            'message' => 'The end user is not a member of this company.',
            'layer' => 'auth',
            'status' => 403,
        ],
        'forbidden' => [
            'message' => 'You do not have permission to perform this action.',
            'layer' => 'auth',
            'status' => 403,
        ],
        'invalid_request' => [
            'message' => 'The request body is invalid.',
            'layer' => 'validation',
            'status' => 422,
        ],
        'invalid_signature' => [
            'message' => 'The webhook signature is invalid.',
            'layer' => 'webhook',
            'status' => 401,
        ],
        'microdeposit_verification_failed' => [
            'message' => 'Micro-deposit verification failed.',
            'layer' => 'policy',
            'status' => 422,
        ],
        'missing_api_key_ability' => [
            'message' => 'The API key is missing a required ability.',
            'layer' => 'auth',
            'status' => 403,
        ],
        'missing_token_scope' => [
            'message' => 'The access token is missing a required scope.',
            'layer' => 'auth',
            'status' => 403,
        ],
        'mock_bank_control_failed' => [
            'message' => 'The mock bank rejected the simulation request.',
            'layer' => 'sandbox',
            'status' => 422,
        ],
        'payment_missing_settlement_ledger' => [
            'message' => 'This payment has no recorded settlement ledger entry.',
            'layer' => 'sandbox',
            'status' => 422,
        ],
        'payment_not_found' => [
            'message' => 'No payment matched the provided transfer reference.',
            'layer' => 'not_found',
            'status' => 404,
        ],
        'payment_not_processing' => [
            'message' => 'The payment is not in a processing state.',
            'layer' => 'sandbox',
            'status' => 422,
        ],
        'payment_not_settled' => [
            'message' => 'The payment must be settled before this simulation.',
            'layer' => 'sandbox',
            'status' => 422,
        ],
        'rate_limit_exceeded' => [
            'message' => 'Too many requests. Retry after the period indicated by Retry-After.',
            'layer' => 'policy',
            'status' => 429,
        ],
        'resource_not_found' => [
            'message' => 'The requested resource was not found.',
            'layer' => 'not_found',
            'status' => 404,
        ],
        'sandbox_only' => [
            'message' => 'This action is only allowed in the sandbox environment.',
            'layer' => 'sandbox',
            'status' => 422,
        ],
        'server_error' => [
            'message' => 'An unexpected error occurred.',
            'layer' => 'internal',
            'status' => 500,
        ],
        'simulation_forbidden_live_environment' => [
            'message' => 'Simulation endpoints require a sandbox API key.',
            'layer' => 'sandbox',
            'status' => 403,
        ],
        'simulation_requires_api_key' => [
            'message' => 'Simulation endpoints require API key authentication.',
            'layer' => 'sandbox',
            'status' => 403,
        ],
        'sandbox_disabled_production' => [
            'message' => 'Sandbox simulation is not available on this deployment.',
            'layer' => 'sandbox',
            'status' => 404,
        ],
        'topup_not_processing' => [
            'message' => 'The top-up is not in a processing state.',
            'layer' => 'sandbox',
            'status' => 422,
        ],
        'unauthenticated_api' => [
            'message' => 'Authentication is required.',
            'layer' => 'auth',
            'status' => 401,
        ],
        'webhook_not_configured' => [
            'message' => 'Inbound webhooks are not configured for this integration.',
            'layer' => 'webhook',
            'status' => 503,
        ],
        'wallet_environment_mismatch' => [
            'message' => 'This wallet exists but its environment does not match your API key. Use a sandbox key for sandbox wallets and a live key for live wallets.',
            'layer' => 'auth',
            'status' => 403,
        ],
        'wallet_not_in_company' => [
            'message' => 'This wallet public_id is not associated with your company.',
            'layer' => 'auth',
            'status' => 403,
        ],
    ],
];
