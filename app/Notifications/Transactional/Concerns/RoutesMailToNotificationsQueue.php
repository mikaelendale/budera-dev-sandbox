<?php

namespace App\Notifications\Transactional\Concerns;

trait RoutesMailToNotificationsQueue
{
    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => (string) config('budera.queues.notifications'),
        ];
    }
}
