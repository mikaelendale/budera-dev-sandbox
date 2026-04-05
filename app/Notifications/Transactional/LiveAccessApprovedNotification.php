<?php

namespace App\Notifications\Transactional;

use App\Models\Company;
use App\Notifications\Transactional\Concerns\RoutesMailToNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class LiveAccessApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable, RoutesMailToNotificationsQueue, SerializesModels;

    public function __construct(
        public Company $company,
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
            ->subject(__('Live access enabled for :name', ['name' => $this->company->name]))
            ->markdown('emails.live-enabled', [
                'company' => $this->company,
            ]);
    }
}
