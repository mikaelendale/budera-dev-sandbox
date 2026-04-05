<?php

namespace App\Notifications\Transactional;

use App\Models\Payment;
use App\Models\WalletAccount;
use App\Notifications\Transactional\Concerns\RoutesMailToNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class LowBalanceNotification extends Notification implements ShouldQueue
{
    use Queueable, RoutesMailToNotificationsQueue, SerializesModels;

    public function __construct(
        public Payment $payment,
        public WalletAccount $walletAccount,
        public int $amountCents,
        public int $balanceCents,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Wallet balance too low for a payment'))
            ->markdown('emails.low-balance', [
                'payment' => $this->payment,
                'wallet' => $this->walletAccount,
                'amountCents' => $this->amountCents,
                'balanceCents' => $this->balanceCents,
            ]);
    }
}
