<?php

namespace App\Policies;

use App\Models\BankLink;
use App\Models\User;

class BankLinkPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, BankLink $bankLink): bool
    {
        return (int) $bankLink->user_id === (int) $user->getKey();
    }

    public function verify(User $user, BankLink $bankLink): bool
    {
        return (int) $bankLink->user_id === (int) $user->getKey();
    }

    public function revoke(User $user, BankLink $bankLink): bool
    {
        return (int) $bankLink->user_id === (int) $user->getKey();
    }
}
