<?php

namespace Database\Factories;

use App\Models\ComplianceFlag;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceFlag>
 */
class ComplianceFlagFactory extends Factory
{
    protected $model = ComplianceFlag::class;

    public function definition(): array
    {
        return [
            'flaggable_type' => Payment::class,
            'flaggable_id' => Payment::factory(),
            'flag_type' => fake()->randomElement(['ofac_match', 'structuring_pattern', 'high_risk_counterparty']),
            'severity' => fake()->randomElement(['low', 'medium', 'high']),
            'details' => [
                'reason' => fake()->sentence(),
            ],
            'resolved_at' => null,
            'resolved_by' => null,
        ];
    }
}
