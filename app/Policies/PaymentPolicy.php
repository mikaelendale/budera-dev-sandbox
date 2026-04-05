<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Models\WalletAccount;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        $wallet = $payment->walletAccount()->first();

        if ($wallet === null) {
            return false;
        }

        return app(WalletAccountPolicy::class)->view($user, $wallet);
    }

    public function create(User $user, WalletAccount $walletAccount): bool
    {
        return app(WalletAccountPolicy::class)->view($user, $walletAccount);
    }
}
