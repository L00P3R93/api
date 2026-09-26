<?php

namespace Database\Factories;

use App\Models\PromoCode;
use App\Models\PromotionCredit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    /**
     * A signup bonus code valid for a week with no redemption limit.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'promotion' => PromotionCredit::PROMOTION_SIGNUP_BONUS,
            'expires_at' => now()->addWeek(),
            'max_redemptions' => null,
            'note' => null,
            'created_by' => 'api_key:1',
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['deactivated_at' => now(), 'deactivated_by' => 'api_key:1']);
    }
}
