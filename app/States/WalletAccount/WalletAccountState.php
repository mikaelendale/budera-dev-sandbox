<?php

namespace App\States\WalletAccount;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class WalletAccountState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(WalletAccountPending::class)
            ->allowTransition(WalletAccountPending::class, WalletAccountActive::class)
            ->allowTransition(WalletAccountActive::class, WalletAccountPaused::class)
            ->allowTransition(WalletAccountActive::class, WalletAccountClosed::class)
            ->allowTransition(WalletAccountActive::class, WalletAccountFrozen::class)
            ->allowTransition(WalletAccountPaused::class, WalletAccountActive::class)
            ->allowTransition(WalletAccountPaused::class, WalletAccountClosed::class)
            ->allowTransition(WalletAccountPaused::class, WalletAccountFrozen::class)
            ->allowTransition(WalletAccountFrozen::class, WalletAccountActive::class)
            ->allowTransition(WalletAccountFrozen::class, WalletAccountClosed::class);
    }
}
