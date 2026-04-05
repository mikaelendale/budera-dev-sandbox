<?php

return [

    /*
    | Allowed documentation slugs (maps to resources/docs/api/{slug}.md).
    */
    'api_pages' => [
        'overview',
        'authentication',
        'quickstart',
        'endpoints',
        'errors',
        'idempotency',
        'webhooks',
        'security',
    ],

    /*
    | Sidebar navigation (order preserved).
    */
    'api_nav' => [
        ['slug' => 'overview', 'label' => 'Overview'],
        ['slug' => 'authentication', 'label' => 'Authentication'],
        ['slug' => 'quickstart', 'label' => 'Quickstart'],
        ['slug' => 'endpoints', 'label' => 'Endpoints'],
        ['slug' => 'errors', 'label' => 'Errors'],
        ['slug' => 'idempotency', 'label' => 'Idempotency'],
        ['slug' => 'webhooks', 'label' => 'Webhooks'],
        ['slug' => 'security', 'label' => 'Security'],
    ],

];
