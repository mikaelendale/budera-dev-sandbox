<?php

namespace App\States\Transfer;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class TransferState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(TransferPending::class)
            ->registerState([
                TransferPending::class,
                TransferCompleted::class,
                TransferFailed::class,
            ])
            ->allowTransition(TransferPending::class, TransferCompleted::class)
            ->allowTransition(TransferPending::class, TransferFailed::class);
    }
}

class TransferPending extends TransferState
{
    protected static string $name = 'pending';
}

class TransferCompleted extends TransferState
{
    protected static string $name = 'completed';
}

class TransferFailed extends TransferState
{
    protected static string $name = 'failed';
}
