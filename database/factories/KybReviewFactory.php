<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\KybReview;
use App\States\KybReview\KybReviewApproved;
use App\States\KybReview\KybReviewPending;
use App\States\KybReview\KybReviewRejected;
use App\States\KybReview\KybReviewUnderReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KybReview>
 */
class KybReviewFactory extends Factory
{
    protected $model = KybReview::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'environment' => 'sandbox',
            'status' => KybReviewPending::class,
            'decided_at' => null,
            'notes' => null,
            'documents' => [],
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => KybReviewApproved::class,
            'decided_at' => now(),
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn () => [
            'status' => KybReviewUnderReview::class,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => KybReviewRejected::class,
            'decided_at' => now(),
        ]);
    }
}
