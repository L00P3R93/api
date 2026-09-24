<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\DepositResolution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolves deposits whose account number matched no customer (status 0). A deposit is either assigned to a
 * customer, which credits their wallet exactly like a plain C2B deposit, or recorded as refunded to the
 * payer (the reversal itself is done on the M-Pesa portal). The M-Pesa record (bill ref, msisdn, amount)
 * is never changed; each resolution is kept in `deposit_resolutions`.
 */
class UnmatchedDepositService
{
    public const COMMAND_ACTOR = 'command:deposits:match-unmatched';

    public function __construct(private WalletDepositService $walletDeposits) {}

    /**
     * Customers an unmatched deposit probably belongs to. `account_no` is the rule C2B uses (bill ref
     * normalised); `phone` compares the payer's number with customers' phone numbers and only works when
     * M-Pesa sent the number unmasked. These are hints: an admin still decides.
     *
     * @return list<array{customer_id: int, name: string, account_no: ?string, phone_no: ?string, match: string}>
     */
    public function suggestionsFor(Deposit $deposit): array
    {
        $suggestions = collect();
        $accountNo = $this->walletDeposits->accountNoFromBillRef($deposit->bill_ref_no);

        if ($accountNo !== '') {
            Customer::where('account_no', $accountNo)->limit(1)->get()
                ->each(fn (Customer $customer) => $suggestions->push($this->suggestion($customer, 'account_no')));
        }

        $phone = $this->lastNineDigits($deposit->msisdn);

        if ($phone !== null) {
            Customer::whereRaw("RIGHT(REPLACE(REPLACE(COALESCE(phone_no, ''), '+', ''), ' ', ''), 9) = ?", [$phone])
                ->limit(5)
                ->get()
                ->each(fn (Customer $customer) => $suggestions->push($this->suggestion($customer, 'phone')));
        }

        return $suggestions->unique('customer_id')->values()->all();
    }

    /**
     * Credit an unmatched deposit to a customer's wallet: ledger `deposit`, excise duty, transaction row,
     * referral first-deposit hook, all as for a C2B payment.
     *
     * @return array{success: bool, message: string, status_code: int, deposit?: Deposit}
     */
    public function assign(int $depositId, int $customerId, string $note, ?string $actor): array
    {
        return DB::transaction(function () use ($depositId, $customerId, $note, $actor) {
            $deposit = Deposit::lockForUpdate()->find($depositId);

            if (! $deposit) {
                return $this->refused('Deposit not found', 404);
            }

            if ((int) $deposit->status !== Deposit::STATUS_UNMATCHED) {
                return $this->refused('Only unmatched deposits can be assigned', 409, $deposit);
            }

            $customer = Customer::find($customerId);

            if (! $customer) {
                return $this->refused('Customer not found', 404);
            }

            $ledgerEntry = $this->walletDeposits->credit($deposit, $customer);

            $deposit->update(['status' => Deposit::STATUS_COMPLETED]);

            DepositResolution::create([
                'deposit_id' => $deposit->id,
                'action' => DepositResolution::ACTION_ASSIGNED,
                'customer_id' => $customer->id,
                'ledger_entry_id' => $ledgerEntry->id,
                'account_no_used' => $customer->account_no,
                'note' => $note,
                'resolved_by' => $actor,
            ]);

            Log::channel('mpesa')->info('Unmatched deposit assigned', ['deposit_id' => $deposit->id, 'customer_id' => $customer->id, 'by' => $actor]);

            return ['success' => true, 'message' => 'Deposit credited to the customer', 'status_code' => 200, 'deposit' => $deposit->fresh('resolution')];
        });
    }

    /**
     * Record that an unmatched deposit was sent back to the payer on the M-Pesa portal. No wallet moves.
     *
     * @return array{success: bool, message: string, status_code: int, deposit?: Deposit}
     */
    public function refund(int $depositId, string $mpesaReference, string $note, ?string $actor): array
    {
        return DB::transaction(function () use ($depositId, $mpesaReference, $note, $actor) {
            $deposit = Deposit::lockForUpdate()->find($depositId);

            if (! $deposit) {
                return $this->refused('Deposit not found', 404);
            }

            if ((int) $deposit->status !== Deposit::STATUS_UNMATCHED) {
                return $this->refused('Only unmatched deposits can be refunded', 409, $deposit);
            }

            if (DepositResolution::where('mpesa_reference', $mpesaReference)->exists()) {
                return $this->refused('That M-Pesa reference is already on another refund', 409, $deposit);
            }

            $deposit->update(['status' => Deposit::STATUS_REFUNDED]);

            DepositResolution::create([
                'deposit_id' => $deposit->id,
                'action' => DepositResolution::ACTION_REFUNDED,
                'mpesa_reference' => $mpesaReference,
                'note' => $note,
                'resolved_by' => $actor,
            ]);

            Log::channel('mpesa')->info('Unmatched deposit refunded', ['deposit_id' => $deposit->id, 'reference' => $mpesaReference, 'by' => $actor]);

            return ['success' => true, 'message' => 'Deposit recorded as refunded', 'status_code' => 200, 'deposit' => $deposit->fresh('resolution')];
        });
    }

    /**
     * Assign every unmatched deposit whose bill ref now matches a customer's account number exactly (the C2B
     * rule), for payers who paid before registering or whose account number was corrected. Never matches on
     * phone numbers. With $dryRun nothing is changed. $actor is recorded as resolved_by.
     *
     * @return Collection<int, array{deposit_id: int, trans_id: ?string, amount: float, bill_ref_no: ?string, customer_id: int, account_no: string, assigned: bool, message: string}>
     */
    public function matchByAccountNumber(bool $dryRun, ?string $actor = self::COMMAND_ACTOR): Collection
    {
        $results = collect();

        Deposit::where('status', Deposit::STATUS_UNMATCHED)
            ->orderBy('id')
            ->chunkById(200, function ($deposits) use ($dryRun, $actor, $results) {
                foreach ($deposits as $deposit) {
                    $accountNo = $this->walletDeposits->accountNoFromBillRef($deposit->bill_ref_no);
                    $customer = $accountNo === '' ? null : Customer::where('account_no', $accountNo)->first();

                    if (! $customer) {
                        continue;
                    }

                    $outcome = $dryRun
                        ? ['success' => true, 'message' => 'Would assign']
                        : $this->assign($deposit->id, $customer->id, "Matched by account number {$accountNo}", $actor);

                    $results->push([
                        'deposit_id' => $deposit->id,
                        'trans_id' => $deposit->trans_id,
                        'amount' => (float) $deposit->trans_amount,
                        'bill_ref_no' => $deposit->bill_ref_no,
                        'customer_id' => $customer->id,
                        'account_no' => $accountNo,
                        'assigned' => ! $dryRun && $outcome['success'],
                        'message' => $outcome['message'],
                    ]);
                }
            });

        return $results;
    }

    /**
     * The last nine digits of a phone number, or null when it is masked, hashed or too short.
     */
    private function lastNineDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) < 9 || strlen($digits) > 12 || str_contains((string) $phone, '*')) {
            return null;
        }

        return substr($digits, -9);
    }

    /**
     * @return array{customer_id: int, name: string, account_no: ?string, phone_no: ?string, match: string}
     */
    private function suggestion(Customer $customer, string $match): array
    {
        return [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'account_no' => $customer->account_no,
            'phone_no' => app(FinanceMasker::class)->phone($customer->phone_no),
            'match' => $match,
        ];
    }

    /**
     * @return array{success: bool, message: string, status_code: int, deposit?: Deposit}
     */
    private function refused(string $message, int $statusCode, ?Deposit $deposit = null): array
    {
        return array_filter([
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode,
            'deposit' => $deposit,
        ], fn ($value) => $value !== null);
    }
}
