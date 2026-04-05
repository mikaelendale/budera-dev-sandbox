<?php

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    public function definition(): array
    {
        return [
            'approvable_type' => Payment::class,
            'approvable_id' => Payment::factory(),
            'requested_by_type' => User::class,
            'requested_by_id' => User::factory(),
            'approval_token' => Str::random(64),
            'expires_at' => now()->addMinutes(30),
            'status' => 'pending',
            'decided_by' => null,
            'decided_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'decided_at' => now(),
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn () => [
            'status' => 'denied',
            'decided_at' => now(),
        ]);
    }
}
