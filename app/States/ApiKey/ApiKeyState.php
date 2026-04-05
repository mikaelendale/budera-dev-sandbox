<?php

namespace App\States\ApiKey;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ApiKeyState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ApiKeyActive::class)
            ->registerState([
                ApiKeyActive::class,
                ApiKeyRotated::class,
                ApiKeyRevoked::class,
            ])
            ->allowTransition(ApiKeyActive::class, ApiKeyRotated::class)
            ->allowTransition(ApiKeyActive::class, ApiKeyRevoked::class)
            ->allowTransition(ApiKeyRotated::class, ApiKeyRevoked::class);
    }
}

class ApiKeyActive extends ApiKeyState
{
    protected static string $name = 'active';
}

class ApiKeyRotated extends ApiKeyState
{
    protected static string $name = 'rotated';
}

class ApiKeyRevoked extends ApiKeyState
{
    protected static string $name = 'revoked';
}
