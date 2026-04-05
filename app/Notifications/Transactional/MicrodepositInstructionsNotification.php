<?php

namespace App\Notifications\Transactional;

use App\Models\BankLink;
use App\Notifications\Transactional\Concerns\RoutesMailToNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class MicrodepositInstructionsNotification extends Notification implements ShouldQueue
{
    use Queueable, RoutesMailToNotificationsQueue, SerializesModels;

    /**
     * @param  list<int>  $amountsCents
     */
    public function __construct(
        public BankLink $bankLink,
        public array $amountsCents,
        public ?string $documentation = null,
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
            ->subject(__('Verify your bank link on Budera'))
            ->markdown('emails.microdeposit-instructions', [
                'bankLink' => $this->bankLink,
                'amountsCents' => $this->amountsCents,
                'documentation' => $this->documentation,
            ]);
    }
}
