<?php

namespace App\Services;

use App\Models\B2C;
use App\Models\Transaction;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class B2CService
{
    public function __construct(private LedgerService $ledgerService) {}

    /**
     * Apply a B2C result callback. Safaricom nests the result under "Result"
     * (`{"Result": {"ConversationID": ..., "ResultCode": ..., "TransactionID": ...}}`); a flat body is
     * still accepted. A result without a ResultCode changes nothing, and a repeated callback finds no
     * transaction because its payment_ref has already been replaced by the receipt.
     */
    public function processB2CResult(array $data): array
    {
        Log::channel('mpesa')->info('MPESA B2C Response: ', $data);

        $result = is_array($data['Result'] ?? null) && isset($data['Result']['ConversationID'])
            ? $data['Result']
            : $data;

        $conversationId = (string) ($result['ConversationID'] ?? '');

        if ($conversationId === '' || ! isset($result['ResultCode'])) {
            Log::channel('mpesa')->warning('MPESA B2C Result Unreadable: ', $data);

            return ['status' => 'invalid'];
        }

        return DB::transaction(function () use ($data, $result, $conversationId) {
            $transaction = Transaction::where('payment_ref', $conversationId)->lockForUpdate()->first();

            if (! $transaction) {
                Log::channel('mpesa')->info('MPESA B2C Transaction Not Found: ', $data);

                return ['status' => 'not_found'];
            }

            $succeeded = (string) $result['ResultCode'] === '0';
            $status = $succeeded ? 2 : 3;
            $receipt = $succeeded ? ($result['TransactionID'] ?? '') : ($result['ResultDesc'] ?? '');

            $transaction->update([
                'payment_ref' => $receipt,
                'status' => $status,
            ]);

            if ($status === 3 && $transaction->payment_type === Withdraw::class && $transaction->payment_id) {
                $withdraw = Withdraw::find($transaction->payment_id);

                if ($withdraw) {
                    $this->ledgerService->reverseWithdrawal($withdraw);
                    $withdraw->update(['disburse' => 3, 'error_message' => $receipt]);
                }
            }

            return ['status' => 'success', 'transaction_id' => $transaction->id];
        });
    }

    public function processB2CBalance(array $data): array
    {
        $accountBalanceString = collect($data['Result']['ResultParameters']['ResultParameter'] ?? [])
            ->firstWhere('Key', 'AccountBalance')['Value'] ?? null;

        $balances = [];

        if ($accountBalanceString) {
            $accounts = explode('&', $accountBalanceString);

            foreach ($accounts as $account) {
                $parts = explode('|', $account);
                $accountName = $parts[0] ?? null;
                $currency = $parts[1] ?? null;
                $balance = $parts[2] ?? 0.00;

                if ($accountName) {
                    $balances[$accountName] = [
                        'currency' => $currency,
                        'balance' => (float) $balance,
                    ];
                }
            }

            B2C::query()->create([
                'amount' => $balances['Utility Account']['balance'] ?? 0,
            ]);
        }

        return $balances;
    }

    public function processB2CTimeout(array $data): array
    {
        Log::channel('mpesa')->info('MPESA B2C Timeout Response: ', $data);

        return $data;
    }
}
