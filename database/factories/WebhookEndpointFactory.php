<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'url' => fake()->url().'/webhook',
            'secret' => Str::random(32),
            'events' => ['payment.settled', 'topup.completed'],
            'environment' => 'sandbox',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
