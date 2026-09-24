<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\ReferralWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralWallet>
 */
class ReferralWalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'balance' => 0,
        ];
    }
}
