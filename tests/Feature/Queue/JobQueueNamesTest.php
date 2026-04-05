<?php

use App\Jobs\DispatchWebhookOutboxJob;
use App\Jobs\ProcessWebhookDeliveryJob;
use App\Jobs\RunComplianceScreenJob;
use App\Notifications\Transactional\CompanyInvitationNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('dispatches compliance job on configured compliance queue', function (): void {
    Queue::fake();

    RunComplianceScreenJob::dispatch(42);

    Queue::assertPushedOn((string) config('budera.queues.compliance'), RunComplianceScreenJob::class);
});

test('dispatches webhook jobs on configured webhooks queue', function (): void {
    Queue::fake();

    ProcessWebhookDeliveryJob::dispatch(1);
    DispatchWebhookOutboxJob::dispatch(2);

    $queue = (string) config('budera.queues.webhooks');
    Queue::assertPushedOn($queue, ProcessWebhookDeliveryJob::class);
    Queue::assertPushedOn($queue, DispatchWebhookOutboxJob::class);
});

test('queues transactional mail on notifications queue', function (): void {
    Queue::fake();

    Notification::route('mail', 'invitee@example.com')->notify(
        new CompanyInvitationNotification('Acme', 'https://budera.test/accept', 'invitee@example.com')
    );

    Queue::assertPushedOn(
        (string) config('budera.queues.notifications'),
        SendQueuedNotifications::class,
    );
});
