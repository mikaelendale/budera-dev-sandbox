<?php

/**
 * API error inventory (Phase 18): codes are defined in config/api_errors.php.
 *
 * - `exception_message_codes`: thrown as InvalidArgumentException messages and passed to ApiErrorResponse::json($e->getMessage()).
 * - `declared_without_scan_match`: used from exception handlers or non-matching patterns; must still exist in config.
 *
 * @return array{
 *     exception_message_codes: list<string>,
 *     declared_without_scan_match: list<string>,
 * }
 */
return [
    'exception_message_codes' => [
        'bank_link_cannot_revoke',
        'bank_link_not_awaiting_verification',
        'microdeposit_verification_failed',
        'mock_bank_control_failed',
    ],
    'declared_without_scan_match' => [
        'approval_action_failed',
        'approval_action_forbidden',
        'company_context_required',
        'company_required',
        'end_user_not_in_company',
        'forbidden',
        'IDEMPOTENCY_KEY_CONFLICT',
        'IDEMPOTENCY_KEY_INVALID',
        'IDEMPOTENCY_KEY_REQUIRED',
        'missing_api_key_ability',
        'missing_token_scope',
        'rate_limit_exceeded',
        'resource_not_found',
        'server_error',
        'simulation_forbidden_live_environment',
        'simulation_requires_api_key',
        'sandbox_disabled_production',
        'unauthenticated_api',
        'wallet_environment_mismatch',
        'wallet_not_in_company',
    ],
];
