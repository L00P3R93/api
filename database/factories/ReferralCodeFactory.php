<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\ReferralCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralCode>
 */
class ReferralCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = Str::upper(Str::random(8));

        return [
            'customer_id' => Customer::factory(),
            'code' => $code,
            'link' => 'https://kadikings.co.ke/r/'.$code,
            'qr_code' => 'https://kadikings.co.ke/qr/'.$code.'.png',
        ];
    }
}
