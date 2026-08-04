<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Wallet::class;

    public function definition(): array
    {
        // Ensure a unique customer_id for each wallet
        static $customerIds = null;

        if ($customerIds === null) {
            $customerIds = Customer::pluck('id')->toArray(); // Get all customer IDs
        }

        return [
            'customer_id' => array_shift($customerIds), // Fetch the next available ID
            'balance' => $this->faker->randomFloat(2, 0, 1000),
        ];
    }
}
