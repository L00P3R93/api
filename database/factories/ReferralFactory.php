<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Referral;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referrer_id' => Customer::factory(),
            'referred_id' => Customer::factory(),
            'code_used' => Str::upper(Str::random(8)),
        ];
    }

    /**
     * The referred customer has been reported verified.
     */
    public function verified(): static
    {
        return $this->state(fn () => ['verified_at' => now()]);
    }
}
