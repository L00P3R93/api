<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\DisputedTransaction;
use App\Models\GameTransaction;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisputedTransaction>
 */
class DisputedTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 1000);

        return [
            'complaint_id' => Complaint::factory(),
            'disputable_type' => GameTransaction::class,
            'disputable_id' => fake()->numberBetween(1, 100000),
            'source_wallet_type' => LedgerEntry::WALLET_TYPE_WALLET,
            'source_wallet_id' => fake()->numberBetween(1, 100000),
            'amount' => $amount,
            'held_amount' => $amount,
            'shortfall_amount' => 0,
            'balance' => $amount,
            'status' => DisputedTransaction::STATUS_HELD,
        ];
    }
}
