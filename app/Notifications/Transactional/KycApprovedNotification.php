<?php

namespace App\Notifications\Transactional;

use App\Models\WalletAccount;
use App\Models\WalletKycVerification;
use App\Notifications\Transactional\Concerns\RoutesMailToNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class KycApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable, RoutesMailToNotificationsQueue, SerializesModels;

    public function __construct(
        public WalletAccount $walletAccount,
        public WalletKycVerification $walletKycVerification,
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
            ->subject(__('Wallet identity verified on Budera'))
            ->markdown('emails.wallet-kyc-approved', [
                'wallet' => $this->walletAccount,
                'kyc' => $this->walletKycVerification,
            ]);
    }
}
