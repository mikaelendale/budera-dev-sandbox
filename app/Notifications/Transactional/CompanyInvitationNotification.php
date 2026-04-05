<?php

namespace App\Notifications\Transactional;

use App\Notifications\Transactional\Concerns\RoutesMailToNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable, RoutesMailToNotificationsQueue;

    public function __construct(
        public string $companyName,
        public string $acceptUrl,
        public string $inviteeEmail,
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
            ->subject(__('You\'re invited to join :company on Budera', ['company' => $this->companyName]))
            ->markdown('emails.company-invitation', [
                'companyName' => $this->companyName,
                'acceptUrl' => $this->acceptUrl,
                'inviteeEmail' => $this->inviteeEmail,
            ]);
    }
}
