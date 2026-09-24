<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\Wallet;

/**
 * Credits a plain M-Pesa deposit to a customer's wallet, the one way it is done for C2B payments and for
 * unmatched deposits resolved later: ledger `deposit`, excise duty when it applies, the transaction row and
 * the referral first-deposit hook. Call inside a DB transaction.
 */
class WalletDepositService
{
    public function __construct(
        private LedgerService $ledgerService,
        private ExciseDutyService $exciseDutyService,
        private ReferralService $referralService,
    ) {}

    /**
     * The customer account number a bill reference points at: the part before the first `#`, with
     * 2547XXXXXXXX and 07XXXXXXXX turned into the 7XXXXXXXX form account numbers are stored in.
     */
    public function accountNoFromBillRef(?string $billRef): string
    {
        return $this->normalizeAccountNo(explode('#', (string) $billRef)[0]);
    }

    public function normalizeAccountNo(string $rawAccountNo): string
    {
        if (preg_match('/^2547\d{8}$/', $rawAccountNo)) {
            return substr($rawAccountNo, 3);
        }

        if (preg_match('/^07\d{8}$/', $rawAccountNo)) {
            return substr($rawAccountNo, 1);
        }

        return $rawAccountNo;
    }

    /**
     * Credit the full deposit to the customer's wallet (creating the wallet if needed) and return the
     * `deposit` ledger entry.
     */
    public function credit(Deposit $deposit, Customer $customer): LedgerEntry
    {
        $wallet = Wallet::firstOrCreate(
            ['customer_id' => $customer->id],
            ['balance' => 0]
        );

        $wallet = Wallet::lockForUpdate()->find($wallet->id);

        $ledgerEntry = $this->ledgerService->recordDeposit($deposit, $wallet, (float) $deposit->trans_amount);

        $exciseDutyCharge = $this->exciseDutyService->chargeIfApplicable($deposit, $wallet, ExciseDutyService::KIND_WALLET_DEPOSIT);

        $wallet->transactions()->create([
            'payment_id' => $deposit->id,
            'payment_ref' => $deposit->trans_id,
            'payment_type' => Deposit::class,
            'amount' => $exciseDutyCharge?->net_amount ?? $deposit->trans_amount,
            'status' => 2,
            'balance_before' => $ledgerEntry->balance_before,
            'balance_after' => $wallet->balance,
        ]);

        $this->referralService->recordDeposit($deposit, $customer->id);

        return $ledgerEntry;
    }
}
