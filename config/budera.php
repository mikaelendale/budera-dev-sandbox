<?php

return [

    /*
    | Logical queue names (Redis/database). Use QUEUE_CONNECTION=redis in staging/production; workers
    | should listen to queue_listen order. Keep composer.json "dev" queue:listen --queue= in sync.
    | Future async payment rail jobs should use queues.payments (see PaymentService / ACH flows).
    */
    'queues' => [
        'default' => env('BUDERA_QUEUE_DEFAULT', 'default'),
        'payments' => env('BUDERA_QUEUE_PAYMENTS', 'payments'),
        'webhooks' => env('BUDERA_QUEUE_WEBHOOKS', 'webhooks'),
        'notifications' => env('BUDERA_QUEUE_NOTIFICATIONS', 'notifications'),
        'compliance' => env('BUDERA_QUEUE_COMPLIANCE', 'compliance'),
    ],

    /*
    | Comma-separated names for php artisan queue:work|listen --queue=...
    */
    'queue_listen' => implode(',', [
        env('BUDERA_QUEUE_DEFAULT', 'default'),
        env('BUDERA_QUEUE_PAYMENTS', 'payments'),
        env('BUDERA_QUEUE_WEBHOOKS', 'webhooks'),
        env('BUDERA_QUEUE_NOTIFICATIONS', 'notifications'),
        env('BUDERA_QUEUE_COMPLIANCE', 'compliance'),
    ]),

    /*
    | Per-minute request budget for /api/v1 (per company when context resolves). Keys must match
    | companies.api_rate_limit_tier (default: default). Unauthenticated hits use IP fallback.
    */
    'api_rate_limits' => [
        'default' => (int) env('BUDERA_API_RATE_LIMIT_DEFAULT', 120),
        'growth' => (int) env('BUDERA_API_RATE_LIMIT_GROWTH', 300),
        'enterprise' => (int) env('BUDERA_API_RATE_LIMIT_ENTERPRISE', 1200),
    ],

    /*
    | Transactional email (queued notifications). Set BUDERA_MAIL_TRANSACTIONAL_ENABLED=false to
    | suppress all outbound sends while keeping code paths (e.g. staging dry-run).
    */
    'mail' => [
        'transactional_enabled' => env('BUDERA_MAIL_TRANSACTIONAL_ENABLED', true),
    ],

    /*
    | URLs referenced from mail templates (path-only segments; use route() where possible).
    */
    'urls' => [
        'payment_approval_show_route' => 'payment-approvals.show',
    ],

    /*
    | Domain audit signing (RSA preferred; HMAC fallback in non-prod when RSA fails).
    | Keys are read only from config — set via .env in production.
    */
    'audit' => [
        'rsa_private_key_pem' => env('BUDERA_RSA_PRIVATE_KEY_PEM'),
        'rsa_public_key_pem' => env('BUDERA_RSA_PUBLIC_KEY_PEM'),
        'hmac_secret' => env('BUDERA_AUTH_HMAC_SECRET'),
    ],

    /*
    | Exact legal copy shown to the user when they verify micro-deposits (ACH debit standing auth).
    */
    'ach' => [
        'standing_authorization_text' => env(
            'BUDERA_ACH_STANDING_AUTHORIZATION_TEXT',
            'By verifying these micro-deposits, you authorize Budera and its banking partners to initiate ACH debits from this linked account for wallet funding and related purposes, in amounts you request, until you revoke this bank link.',
        ),
    ],

    'oauth' => [
        'sandbox_auto_approve' => env('OAUTH_SANDBOX_AUTO_APPROVE', false),
        /*
        | Passport token scopes (id => human description). Kept in sync with Passport::tokensCan.
        */
        'token_scopes' => [
            'wallet:read' => 'Read wallet balance and activity',
            'wallet:pay' => 'Initiate payments on your behalf',
            'wallet:approve' => 'Approve or deny held payment requests',
            'wallet:topup' => 'Add funds to your wallet',
            'wallet:transfer' => 'Move money between accounts',
            'wallet:link' => 'Link external bank accounts for funding (micro-deposit verification)',
            'sandbox:simulate' => 'Use sandbox-only simulation endpoints (settlement, returns, KYC, micro-deposits)',
            'webhooks:manage' => 'Create, update, and test company webhook endpoints',
        ],
        /*
        | Scopes highlighted in consent UI when NOT granted (risk-relevant capabilities).
        */
        'sensitive_scope_ids' => [
            'wallet:pay',
            'wallet:approve',
            'wallet:topup',
            'wallet:transfer',
            'wallet:link',
            'sandbox:simulate',
            'webhooks:manage',
        ],
    ],

    /*
    | When true, KYC may be completed immediately even for live-environment wallets (hosted session
    | and end-user verify-identity). Sandbox wallets always use mock auto-complete without this flag.
    */
    'sandbox' => [
        'allow_force_kyc_approve' => env('BUDERA_SANDBOX_FORCE_KYC_APPROVE', false),
    ],

    /*
    | Cookie name for company dashboard sandbox/live selector (web session only).
    */
    'dashboard_environment_cookie' => env(
        'BUDERA_DASHBOARD_ENVIRONMENT_COOKIE',
        'budera_dashboard_environment',
    ),

    /*
    | Sandbox micro-deposit amounts (cents) for external bank link verification.
    | Documented for developers; MockBankLinkService compares verify payloads to these values.
    */
    'bank_link' => [
        'sandbox_microdeposit_cents' => [12, 34],
        'session_ttl_hours' => (int) env('BUDERA_BANK_LINK_SESSION_TTL_HOURS', 72),
    ],

    /*
    | Outbound webhook event names companies may subscribe to (plus "*" for all).
    | test.ping is used only for the dashboard "Test" action.
    */
    'outbound_webhook_events' => [
        'account.active',
        'account.frozen',
        'account.unfrozen',
        'kyc.approved',
        'kyc.failed',
        'kyc.needs_info',
        'kyb.approved',
        'live.enabled',
        'payment.approved',
        'payment.processing',
        'payment.failed',
        'payment.settled',
        'payment.returned',
        'payment.held.approval_required',
        'payment.held.anomaly',
        'topup.settled',
        'topup.failed',
        'test.ping',
    ],

];
