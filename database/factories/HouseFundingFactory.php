<?php

namespace Database\Factories;

use App\Models\HouseFunding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass deposit_id and ledger_entry_id: a house funding always points at a real deposit and its ledger
 * credit, so tests usually create one through HouseFundingService::fund() instead.
 *
 * @extends Factory<HouseFunding>
 */
class HouseFundingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'note' => fake()->sentence(4),
            'recorded_by' => 'api_key:1',
        ];
    }

    public function voided(string $reason = 'entered in error'): static
    {
        return $this->state(fn () => [
            'voided_at' => now(),
            'voided_by' => 'api_key:1',
            'void_reason' => $reason,
        ]);
    }
}
