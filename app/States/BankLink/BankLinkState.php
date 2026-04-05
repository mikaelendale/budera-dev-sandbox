<?php

namespace App\States\BankLink;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class BankLinkState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(BankLinkInitiated::class)
            ->registerState([
                BankLinkInitiated::class,
                BankLinkMicrodepositSent::class,
                BankLinkVerified::class,
                BankLinkFailed::class,
                BankLinkRevoked::class,
            ])
            ->allowTransition(BankLinkInitiated::class, BankLinkMicrodepositSent::class)
            ->allowTransition(BankLinkMicrodepositSent::class, BankLinkVerified::class)
            ->allowTransition(BankLinkMicrodepositSent::class, BankLinkFailed::class)
            ->allowTransition(BankLinkMicrodepositSent::class, BankLinkRevoked::class)
            ->allowTransition(BankLinkVerified::class, BankLinkRevoked::class);
    }
}

class BankLinkInitiated extends BankLinkState
{
    protected static string $name = 'initiated';
}

class BankLinkMicrodepositSent extends BankLinkState
{
    protected static string $name = 'microdeposit_sent';
}

class BankLinkVerified extends BankLinkState
{
    protected static string $name = 'verified';
}

class BankLinkFailed extends BankLinkState
{
    protected static string $name = 'failed';
}

class BankLinkRevoked extends BankLinkState
{
    protected static string $name = 'revoked';
}
