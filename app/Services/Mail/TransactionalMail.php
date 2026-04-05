<?php

namespace App\Services\Mail;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class TransactionalMail
{
    public static function enabled(): bool
    {
        return (bool) config('budera.mail.transactional_enabled', true);
    }

    public static function notifyUser(?User $user, Notification $notification): void
    {
        if (! self::enabled() || $user === null) {
            return;
        }

        $email = $user->email;
        if (! is_string($email) || $email === '') {
            return;
        }

        $user->notify($notification);
    }

    public static function notifyEmail(string $email, Notification $notification): void
    {
        if (! self::enabled() || trim($email) === '') {
            return;
        }

        NotificationFacade::route('mail', $email)->notify($notification);
    }
}
