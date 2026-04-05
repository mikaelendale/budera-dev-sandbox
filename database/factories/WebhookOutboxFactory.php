<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WebhookOutbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookOutbox>
 */
class WebhookOutboxFactory extends Factory
{
    protected $model = WebhookOutbox::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'event' => 'payment.settled',
            'event_id' => 'evt_'.fake()->unique()->numerify('########'),
            'environment' => 'sandbox',
            'payload' => ['type' => 'payment.settled', 'data' => ['amount' => 1000]],
            'destination_url' => fake()->url().'/webhook',
            'destination_key' => null,
            'attempts' => 0,
            'status' => 'queued',
            'last_error' => null,
            'reserved_at' => null,
        ];
    }
}
