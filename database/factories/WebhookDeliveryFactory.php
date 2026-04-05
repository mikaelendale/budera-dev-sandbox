<?php

namespace Database\Factories;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'webhook_outbox_id' => null,
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => 'payment.settled',
            'event_id' => 'evt_'.fake()->unique()->numerify('########'),
            'payload' => ['type' => 'payment.settled', 'data' => ['id' => fake()->uuid()]],
            'status' => 'queued',
            'attempts' => 0,
            'last_attempted_at' => null,
            'response_status' => null,
            'response_body' => null,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'attempts' => 1,
            'last_attempted_at' => now(),
            'response_status' => 200,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'attempts' => 3,
            'last_attempted_at' => now(),
            'response_status' => 500,
            'response_body' => 'Internal Server Error',
        ]);
    }
}
