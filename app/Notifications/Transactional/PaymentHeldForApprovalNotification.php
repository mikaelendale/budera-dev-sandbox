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

class PaymentHeldForApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable, RoutesMailToNotificationsQueue, SerializesModels;

    public function __construct(
        public Payment $payment,
        public WalletAccount $walletAccount,
        public string $approvalUrl,
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
        $amountUsd = $this->payment->amount_usd !== null ? (string) $this->payment->amount_usd : '';

        return (new MailMessage)
            ->subject(__('Payment needs your approval'))
            ->markdown('emails.payment-held-approval', [
                'payment' => $this->payment,
                'wallet' => $this->walletAccount,
                'approvalUrl' => $this->approvalUrl,
                'amountUsd' => $amountUsd,
                'payeeRef' => $this->payment->payee_ref,
            ]);
    }
}
