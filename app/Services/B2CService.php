<?php

namespace App\Services;

use App\Models\B2C;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class B2CService
{
    public function processB2CResult(array $data): array
    {
        Log::channel('mpesa')->info('MPESA B2C Response: ', $data);

        $transaction = Transaction::where('payment_ref', $data['ConversationID'] ?? '')->first();

        if (! $transaction) {
            Log::channel('mpesa')->info('MPESA B2C Transaction Not Found: ', $data);

            return ['status' => 'not_found'];
        }

        $status = ($data['ResultCode'] ?? 0) == 0 ? 2 : 3;
        $receipt = ($data['ResultCode'] ?? 0) == 0 ? ($data['TransactionID'] ?? '') : ($data['ResultDesc'] ?? '');

        $transaction->update([
            'payment_ref' => $receipt,
            'status' => $status,
        ]);

        return ['status' => 'success', 'transaction_id' => $transaction->id];
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
