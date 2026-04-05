<?php

namespace Database\Factories;

use App\Models\PartnerBankIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerBankIntegration>
 */
class PartnerBankIntegrationFactory extends Factory
{
    protected $model = PartnerBankIntegration::class;

    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['mock_bank', 'column']),
            'label' => fake()->company().' Integration',
            'environment' => 'sandbox',
            'base_url' => fake()->url(),
            'credentials' => [
                'outbound_api_secret' => fake()->sha256(),
                'inbound_webhook_secret' => fake()->sha256(),
            ],
            'is_active' => true,
        ];
    }
}
