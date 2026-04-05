<?php

namespace App\States\WalletKycVerification;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class WalletKycVerificationState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(WalletKycVerificationPending::class)
            ->allowTransition(WalletKycVerificationNotStarted::class, WalletKycVerificationPending::class)
            ->allowTransition(WalletKycVerificationPending::class, WalletKycVerificationApproved::class)
            ->allowTransition(WalletKycVerificationPending::class, WalletKycVerificationRejected::class)
            ->allowTransition(WalletKycVerificationPending::class, WalletKycVerificationNeedsInfo::class)
            ->allowTransition(WalletKycVerificationNeedsInfo::class, WalletKycVerificationPending::class)
            ->allowTransition(WalletKycVerificationApproved::class, WalletKycVerificationRejected::class);
    }
}
