<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\ExciseDutyCharge;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExciseDutyCharge>
 */
class ExciseDutyChargeFactory extends Factory
{
    /**
     * A 5% charge with its own deposit, customer, wallet and excise ledger entry.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gross = (float) fake()->numberBetween(10, 5000);
        $rate = 0.05;
        $excise = round($gross * $rate, 2);

        return [
            'deposit_id' => fn () => Deposit::create([
                'trans_id' => strtoupper(Str::random(10)),
                'trans_type' => 'Pay Bill',
                'trans_time' => now(),
                'trans_amount' => $gross,
                'short_code' => '12345',
                'bill_ref_no' => 'EXCISE',
                'msisdn' => '254712345678',
                'name' => fake()->name(),
                'status' => 2,
            ])->id,
            'customer_id' => fn () => Customer::factory()->create()->id,
            'wallet_id' => fn (array $attributes) => Wallet::firstOrCreate(['customer_id' => $attributes['customer_id']], ['balance' => 0])->id,
            'ledger_entry_id' => fn (array $attributes) => LedgerEntry::create([
                'entry_id' => (string) Str::uuid(),
                'entry_type' => 'excise_duty',
                'wallet_type' => LedgerEntry::WALLET_TYPE_WALLET,
                'wallet_id' => $attributes['wallet_id'],
                'customer_id' => $attributes['customer_id'],
                'debit' => $excise,
                'credit' => 0,
                'balance_before' => $gross,
                'balance_after' => $gross - $excise,
                'status' => 'settled',
            ])->id,
            'gross_amount' => $gross,
            'rate' => $rate,
            'excise_amount' => $excise,
            'net_amount' => $gross - $excise,
            'status' => ExciseDutyCharge::STATUS_CHARGED,
            'charged_at' => now(),
        ];
    }

    public function reversed(): static
    {
        return $this->state(fn () => ['status' => ExciseDutyCharge::STATUS_REVERSED]);
    }
}
