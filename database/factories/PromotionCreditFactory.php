<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\PromotionCredit;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromotionCredit>
 */
class PromotionCreditFactory extends Factory
{
    /**
     * A KES 20 net signup bonus grossed up for 5% excise, with its customer, wallet and both ledger entries.
     * Only the rows are created: wallet balances are not changed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => fn () => Customer::factory()->create()->id,
            'wallet_id' => fn (array $attributes) => Wallet::firstOrCreate(['customer_id' => $attributes['customer_id']], ['balance' => 0])->id,
            'promotion' => PromotionCredit::PROMOTION_SIGNUP_BONUS,
            'gross_amount' => 21.05,
            'rate' => 0.05,
            'excise_amount' => 1.05,
            'net_amount' => 20.00,
            'status' => PromotionCredit::STATUS_GRANTED,
            'ledger_entry_id' => fn (array $attributes) => $this->ledgerEntry($attributes['wallet_id'], $attributes['customer_id'], 0, (float) $attributes['gross_amount'])->id,
            'house_ledger_entry_id' => fn (array $attributes) => $this->ledgerEntry((int) config('wallets.house_wallet_id', 1), null, (float) $attributes['gross_amount'], 0)->id,
        ];
    }

    private function ledgerEntry(int $walletId, ?int $customerId, float $debit, float $credit): LedgerEntry
    {
        return LedgerEntry::create([
            'entry_id' => (string) Str::uuid(),
            'entry_type' => 'promo_credit',
            'wallet_type' => LedgerEntry::WALLET_TYPE_WALLET,
            'wallet_id' => $walletId,
            'customer_id' => $customerId,
            'debit' => $debit,
            'credit' => $credit,
            'balance_before' => 0,
            'balance_after' => $credit - $debit,
            'status' => 'settled',
        ]);
    }
}
