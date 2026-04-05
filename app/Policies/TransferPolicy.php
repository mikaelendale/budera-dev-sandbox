<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;
use App\Models\WalletAccount;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transfer $transfer): bool
    {
        $from = $transfer->fromWalletAccount()->first();
        $to = $transfer->toWalletAccount()->first();

        $walletPolicy = app(WalletAccountPolicy::class);

        if ($from !== null && $walletPolicy->view($user, $from)) {
            return true;
        }

        if ($to !== null && $walletPolicy->view($user, $to)) {
            return true;
        }

        return false;
    }

    public function create(User $user, WalletAccount $from, WalletAccount $to): bool
    {
        $walletPolicy = app(WalletAccountPolicy::class);

        return $walletPolicy->view($user, $from) && $walletPolicy->view($user, $to);
    }
}
