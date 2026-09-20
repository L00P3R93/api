<?php

namespace App\Services;

use App\Models\Coin;
use App\Models\CompetitionWallet;
use App\Models\Deposit;
use App\Models\FinancialSnapshot;
use App\Models\GameWallet;
use App\Models\MpesaBalance;
use App\Models\PendingBalance;
use App\Models\Wallet;

class FinanceSnapshotService
{
    /**
     * Record today's position. Running it again on the same day overwrites that day's row.
     *
     * Customer wallets and coins exclude the house wallet and the configured test customers.
     * Stuck escrow is money still sitting in a game or competition wallet that is no longer open.
     */
    public function takeSnapshot(): FinancialSnapshot
    {
        $houseWalletId = (int) config('wallets.house_wallet_id', 1);
        $testCustomerIds = config('finance.test_customer_ids');

        $stuckEscrow = GameWallet::where('status', '!=', 1)->where('balance', '>', 0)->sum('balance')
            + CompetitionWallet::where('status', '!=', 1)->where('balance', '>', 0)->sum('balance');

        $coins = Coin::whereNotIn('customer_id', $testCustomerIds)->sum('coins');

        return FinancialSnapshot::updateOrCreate(
            ['snapshot_date' => now()->toDateString()],
            [
                'customer_wallets_total' => $this->money(
                    Wallet::where('id', '!=', $houseWalletId)->whereNotIn('customer_id', $testCustomerIds)->sum('balance')
                ),
                'house_wallet_balance' => $this->money(Wallet::whereKey($houseWalletId)->sum('balance')),
                'game_escrow_total' => $this->money(GameWallet::where('status', 1)->sum('balance')),
                'competition_escrow_total' => $this->money(CompetitionWallet::where('status', 1)->sum('balance')),
                'stuck_escrow_total' => $this->money($stuckEscrow),
                'coin_liability' => $this->money($coins * config('finance.coin_rate')),
                'pending_holds_total' => $this->money(PendingBalance::where('status', 'holding')->sum('amount')),
                'unmatched_deposits_total' => $this->money(Deposit::where('status', 0)->sum('trans_amount')),
                'mpesa_balances' => $this->latestMpesaBalances(),
                'taken_at' => now(),
            ]
        );
    }

    /**
     * Latest balance per M-Pesa account, e.g. ['b2c' => ['Utility Account' => ['amount' => 1200.0, 'as_of' => '...']]].
     *
     * @return array<string, array<string, array{amount: float, as_of: string}>>|null
     */
    private function latestMpesaBalances(): ?array
    {
        $latest = MpesaBalance::whereIn(
            'id',
            MpesaBalance::selectRaw('MAX(id)')->groupBy('type', 'account_name')
        )->get();

        if ($latest->isEmpty()) {
            return null;
        }

        return $latest
            ->groupBy('type')
            ->map(fn ($rows) => $rows->mapWithKeys(fn (MpesaBalance $row) => [
                (string) $row->account_name => [
                    'amount' => (float) $row->amount,
                    'as_of' => $row->created_at->toIso8601String(),
                ],
            ])->all())
            ->all();
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
