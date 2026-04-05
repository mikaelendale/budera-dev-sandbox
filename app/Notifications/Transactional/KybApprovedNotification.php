<?php

namespace App\Notifications\Transactional;

use App\Models\Company;
use App\Models\KybReview;
use App\Notifications\Transactional\Concerns\RoutesMailToNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class KybApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable, RoutesMailToNotificationsQueue, SerializesModels;

    public function __construct(
        public Company $company,
        public KybReview $review,
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
            ->subject(__('KYB approved for :name on Budera', ['name' => $this->company->name]))
            ->markdown('emails.kyb-approved', [
                'company' => $this->company,
                'review' => $this->review,
            ]);
    }
}
