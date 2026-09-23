<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'complaint_id' => (string) Str::uuid(),
            'customer_id' => Customer::factory(),
            'subject_type' => Complaint::SUBJECT_GAME,
            'reason' => fake()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'status' => Complaint::STATUS_PENDING,
            'filed_by' => 'api_key:1',
        ];
    }

    public function closed(string $status = Complaint::STATUS_REJECTED): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'resolution_note' => fake()->sentence(),
            'closed_by' => 'api_key:1',
            'closed_at' => now(),
        ]);
    }
}
