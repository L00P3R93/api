<?php

namespace Database\Factories;

use App\Models\FinancialSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialSnapshot>
 */
class FinancialSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'snapshot_date' => fake()->unique()->date(),
            'customer_wallets_total' => fake()->randomFloat(2, 0, 100000),
            'house_wallet_balance' => fake()->randomFloat(2, 0, 10000),
            'game_escrow_total' => fake()->randomFloat(2, 0, 5000),
            'competition_escrow_total' => fake()->randomFloat(2, 0, 5000),
            'stuck_escrow_total' => 0,
            'coin_liability' => 0,
            'pending_holds_total' => 0,
            'unmatched_deposits_total' => 0,
            'mpesa_balances' => null,
            'taken_at' => now(),
        ];
    }
}
