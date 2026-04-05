<?php

namespace App\States\Payment;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class PaymentState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PaymentPending::class)
            ->registerState([
                PaymentPending::class,
                PaymentApproved::class,
                PaymentProcessing::class,
                PaymentSettled::class,
                PaymentFailed::class,
                PaymentReturned::class,
                PaymentHeldAnomaly::class,
                PaymentHeldApproval::class,
            ])
            ->allowTransition(PaymentPending::class, PaymentApproved::class)
            ->allowTransition(PaymentPending::class, PaymentFailed::class)
            ->allowTransition(PaymentApproved::class, PaymentFailed::class)
            ->allowTransition(PaymentPending::class, PaymentHeldApproval::class)
            ->allowTransition(PaymentPending::class, PaymentHeldAnomaly::class)
            ->allowTransition(PaymentApproved::class, PaymentProcessing::class)
            ->allowTransition(PaymentProcessing::class, PaymentSettled::class)
            ->allowTransition(PaymentProcessing::class, PaymentFailed::class)
            ->allowTransition(PaymentProcessing::class, PaymentReturned::class)
            ->allowTransition(PaymentSettled::class, PaymentReturned::class)
            ->allowTransition(PaymentProcessing::class, PaymentHeldAnomaly::class)
            ->allowTransition(PaymentProcessing::class, PaymentHeldApproval::class)
            ->allowTransition(PaymentHeldAnomaly::class, PaymentApproved::class)
            ->allowTransition(PaymentHeldAnomaly::class, PaymentFailed::class)
            ->allowTransition(PaymentHeldApproval::class, PaymentApproved::class)
            ->allowTransition(PaymentHeldApproval::class, PaymentFailed::class);
    }
}

class PaymentPending extends PaymentState
{
    protected static string $name = 'pending';
}

class PaymentApproved extends PaymentState
{
    protected static string $name = 'approved';
}

class PaymentProcessing extends PaymentState
{
    protected static string $name = 'processing';
}

class PaymentSettled extends PaymentState
{
    protected static string $name = 'settled';
}

class PaymentFailed extends PaymentState
{
    protected static string $name = 'failed';
}

class PaymentReturned extends PaymentState
{
    protected static string $name = 'returned';
}

class PaymentHeldAnomaly extends PaymentState
{
    protected static string $name = 'held_anomaly';
}

class PaymentHeldApproval extends PaymentState
{
    protected static string $name = 'held_approval';
}
