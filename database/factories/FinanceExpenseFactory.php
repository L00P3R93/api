<?php

namespace Database\Factories;

use App\Models\FinanceExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceExpense>
 */
class FinanceExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_date' => fake()->dateTimeBetween('-30 days')->format('Y-m-d'),
            'category' => fake()->randomElement(config('finance.expense_categories')),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'description' => fake()->sentence(4),
            'reference' => null,
            'entered_by' => 'api_key:1',
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
