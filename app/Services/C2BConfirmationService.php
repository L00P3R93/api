<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;

class C2BConfirmationService
{
    public function __construct(private LedgerService $ledgerService) {}

    public function processCallback(array $depositData): array
    {
        $transId = $depositData['trans_id'];

        $existingDeposit = Deposit::where('trans_id', $transId)->first();
        if ($existingDeposit) {
            Log::channel('mpesa')->info('MPESA Confirmation Duplicate Skipped', ['trans_id' => $transId]);

            return [
                'ResultCode' => '0',
                'ResultDesc' => 'Already processed',
                'status' => 200,
            ];
        }

        $deposit = Deposit::create($depositData);

        $billRefParts = explode('#', $deposit->bill_ref_no);
        $rawAccountNo = $billRefParts[0];
        $coinValue = $billRefParts[1] ?? null;
        $type = $billRefParts[2] ?? null;
        $referralCode = $billRefParts[3] ?? null;

        $normalizedAccountNo = $this->normalizeAccountNo($rawAccountNo);

        $customer = Customer::where('id_no', $rawAccountNo)
            ->orWhere('account_no', $normalizedAccountNo)
            ->first();

        if (! $customer) {
            $deposit->update(['status' => 0]);
            Log::channel('mpesa')->error('MPESA Confirmation Received [Invalid Customer]:', $depositData);

            return [
                'ResultCode' => 'C2B00016',
                'ResultDesc' => 'Invalid customer',
                'status' => 500,
            ];
        }

        $this->processByType($type, $coinValue, $deposit, $customer, $referralCode);

        $deposit->update(['status' => 2]);

        return [
            'ResultCode' => '0',
            'ResultDesc' => 'Accepted',
            'status' => 201,
        ];
    }

    private function normalizeAccountNo(string $rawAccountNo): string
    {
        if (preg_match('/^2547\d{8}$/', $rawAccountNo)) {
            return substr($rawAccountNo, 3);
        }

        if (preg_match('/^07\d{8}$/', $rawAccountNo)) {
            return substr($rawAccountNo, 1);
        }

        return $rawAccountNo;
    }

    private function processByType(
        ?string $type,
        ?string $coinValue,
        Deposit $deposit,
        Customer $customer,
        ?string $referralCode
    ): void {
        if ($type === 'load') {
            $this->processLoad($coinValue, $deposit, $customer, $referralCode);
        } elseif ($type === 'gift' || $type === 'emoji') {
            $this->processGiftOrEmoji($type, $coinValue, $deposit, $customer, $referralCode);
        } else {
            $this->processDefault($deposit, $customer);
        }
    }

    private function processLoad(
        ?string $coinValue,
        Deposit $deposit,
        Customer $customer,
        ?string $referralCode
    ): void {
        $wallet = Wallet::firstOrCreate(
            ['customer_id' => $customer->id],
            ['balance' => 0]
        );

        $amountToAdd = (float) $coinValue;

        $ledgerEntry = $this->ledgerService->recordDeposit($deposit, $wallet, $amountToAdd);

        $wallet->transactions()->create([
            'payment_id' => $deposit->id,
            'payment_ref' => $deposit->trans_id,
            'payment_type' => Deposit::class,
            'amount' => $amountToAdd,
            'status' => 2,
            'balance_before' => $ledgerEntry->balance_before,
            'balance_after' => $ledgerEntry->balance_after,
        ]);

        $customer->purchases()->create([
            'deposit_id' => $deposit->id,
            'purchase_type' => 'load',
            'amount' => $deposit->trans_amount,
            'value' => $amountToAdd,
            'referral_code' => $referralCode,
        ]);
    }

    private function processGiftOrEmoji(
        string $type,
        ?string $coinValue,
        Deposit $deposit,
        Customer $customer,
        ?string $referralCode
    ): void {
        $amountToAdd = (float) $coinValue;

        $customer->purchases()->create([
            'deposit_id' => $deposit->id,
            'purchase_type' => $type,
            'amount' => $deposit->trans_amount,
            'value' => $amountToAdd,
            'referral_code' => $referralCode,
        ]);
    }

    private function processDefault(Deposit $deposit, Customer $customer): void
    {
        $wallet = Wallet::firstOrCreate(
            ['customer_id' => $customer->id],
            ['balance' => 0]
        );

        $amountToAdd = $deposit->trans_amount;

        $ledgerEntry = $this->ledgerService->recordDeposit($deposit, $wallet, (float) $amountToAdd);

        $wallet->transactions()->create([
            'payment_id' => $deposit->id,
            'payment_ref' => $deposit->trans_id,
            'payment_type' => Deposit::class,
            'amount' => $amountToAdd,
            'status' => 2,
            'balance_before' => $ledgerEntry->balance_before,
            'balance_after' => $ledgerEntry->balance_after,
        ]);
    }
}
