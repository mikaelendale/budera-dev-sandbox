<?php

namespace App\States\Topup;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class TopupState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(TopupPending::class)
            ->registerState([
                TopupPending::class,
                TopupProcessing::class,
                TopupSettled::class,
                TopupFailed::class,
                TopupReturned::class,
            ])
            ->allowTransition(TopupPending::class, TopupProcessing::class)
            ->allowTransition(TopupPending::class, TopupFailed::class)
            ->allowTransition(TopupProcessing::class, TopupSettled::class)
            ->allowTransition(TopupProcessing::class, TopupFailed::class)
            ->allowTransition(TopupProcessing::class, TopupReturned::class);
    }
}

class TopupPending extends TopupState
{
    protected static string $name = 'pending';
}

class TopupProcessing extends TopupState
{
    protected static string $name = 'processing';
}

class TopupSettled extends TopupState
{
    protected static string $name = 'settled';
}

class TopupFailed extends TopupState
{
    protected static string $name = 'failed';
}

class TopupReturned extends TopupState
{
    protected static string $name = 'returned';
}
