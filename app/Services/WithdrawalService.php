<?php

namespace App\Services;

use App\Exceptions\MpesaApiException;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalService
{
    public function __construct(
        private LedgerService $ledgerService,
        private MpesaService $mpesaService
    ) {}

    public function initiateWithdrawal(string $identifier, float $amount): array
    {
        $customer = Customer::where('id', $identifier)
            ->orWhere('account_no', $identifier)
            ->first();

        if (! $customer) {
            return ['success' => false, 'message' => 'Customer not found', 'status_code' => 404];
        }

        $wallet = $customer->wallet;
        if (! $wallet) {
            return ['success' => false, 'message' => 'Wallet not found', 'status_code' => 404];
        }

        $canWithdraw = DB::transaction(function () use ($wallet, $amount) {
            $lockedWallet = Wallet::lockForUpdate()->find($wallet->id);

            if ($lockedWallet->balance < $amount) {
                return false;
            }

            $lockedWallet->balance -= $amount;
            $lockedWallet->save();

            return true;
        });

        if (! $canWithdraw) {
            return ['success' => false, 'message' => 'Insufficient wallet balance for withdrawal', 'status_code' => 400];
        }

        $transaction = $wallet->transactions()->create([
            'payment_id' => null,
            'payment_ref' => null,
            'payment_type' => Withdraw::class,
            'amount' => $amount,
            'status' => 1,
        ]);

        $withdraw = Withdraw::create([
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'disburse' => 1,
        ]);

        $phone_no = $customer->phone_no ?? null;
        if (! $phone_no) {
            return ['success' => false, 'message' => 'Customer phone number not found', 'status_code' => 400];
        }

        $userParams = [
            'Amount' => $withdraw->amount,
            'PartyB' => $phone_no,
            'Remarks' => 'Business Payment',
        ];

        try {
            $response = $this->mpesaService->b2c($userParams);
        } catch (MpesaApiException $e) {
            Log::channel('mpesa')->error('MPESA B2C Error: '.$e->getMessage());

            $transaction->update([
                'payment_id' => $withdraw->id,
                'status' => 3,
            ]);

            $withdraw->update([
                'disburse' => 3,
                'error_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage(), 'status_code' => 500];
        }

        Log::channel('mpesa')->info('MPESA B2C Response', $response);

        if (isset($response['ResponseCode']) && $response['ResponseCode'] == 0) {
            $ledgerEntry = $this->ledgerService->recordWithdrawal($withdraw, $wallet, (float) $amount);

            $transaction->update([
                'payment_id' => $withdraw->id,
                'payment_ref' => $response['ConversationID'],
                'status' => 2,
                'balance_before' => $ledgerEntry->balance_before,
                'balance_after' => $ledgerEntry->balance_after,
            ]);

            $withdraw->update([
                'disburse' => 2,
                'receipt' => $response['ConversationID'] ?? 'N/A',
            ]);

            return [
                'success' => true,
                'message' => 'success',
                'ledger_entry_id' => $ledgerEntry->entry_id,
                'status_code' => 201,
            ];
        } else {
            $transaction->update([
                'payment_id' => $withdraw->id,
                'status' => 3,
            ]);

            $withdraw->update([
                'disburse' => 3,
                'receipt' => $response['ResponseCode'] ?? null,
                'error_message' => $response['ResponseDescription'] ?? 'Unknown Error',
            ]);

            return ['success' => false, 'message' => 'Unknown Error', 'status_code' => 500];
        }
    }

    public function approveWithdrawal(string $identifier): array
    {
        $transaction = Transaction::find($identifier);
        if (! $transaction) {
            return ['success' => false, 'message' => 'Transaction not found', 'status_code' => 404];
        }

        if ($transaction->payment_type == Withdraw::class && $transaction->status == 1) {
            $withdrawal = Withdraw::create([
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
            ]);

            $transaction->payment_id = $withdrawal->id;
            $transaction->status = '2';
            $transaction->save();

            return [
                'success' => true,
                'message' => 'Withdrawal request approved successfully.',
                'withdraw' => $withdrawal,
                'transaction' => $transaction,
                'status_code' => 200,
            ];
        }

        return ['success' => false, 'message' => 'Invalid or already processed withdrawal request.', 'status_code' => 400];
    }

    public function disburse(string $identifier): array
    {
        $withdraw = Withdraw::find($identifier);
        if (! $withdraw) {
            return ['success' => false, 'message' => 'Transaction not found', 'status_code' => 404];
        }

        if ($withdraw->status == 1 && $withdraw->disburse == 1 && $withdraw->receipt == null && $withdraw->transactions()->first()->payment_type == Withdraw::class) {
            $userParams = [
                'Amount' => $withdraw->amount,
                'PartyB' => $withdraw->transactions()->first()->wallet->customer->phone_no,
                'Remarks' => 'Business Payment',
            ];

            try {
                $response = $this->mpesaService->b2c($userParams);
            } catch (MpesaApiException $e) {
                $withdraw->disburse = 3;
                $withdraw->error_message = $e->getMessage();
                $withdraw->save();

                return [
                    'success' => false,
                    'message' => 'Disbursement failed: '.$e->getMessage(),
                    'status_code' => 500,
                ];
            }

            if ($response['ResponseCode'] == 0) {
                $withdraw->disburse = 2;
                $withdraw->receipt = $response['ConversationID'];
            } else {
                $withdraw->disburse = 3;
                $withdraw->receipt = $response['errorCode'] ?? null;
                $withdraw->error_message = $response['errorMessage'] ?? $response['ResponseDescription'] ?? 'Unknown Error';
            }
            $withdraw->save();

            return [
                'success' => true,
                'message' => 'Disbursement processed successfully.',
                'withdraw' => $withdraw,
                'status_code' => 200,
            ];
        }

        return ['success' => false, 'message' => 'Invalid or already processed Disburse request.', 'status_code' => 400];
    }
}
