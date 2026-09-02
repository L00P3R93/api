<?php

namespace App\Services;

use App\Models\Customer;
use App\Mpesa\Init as Mpesa;
use Illuminate\Support\Facades\Log;

class StkService
{
    public function initiateStkDeposit(string $identifier, float $amount): array
    {
        $customer = Customer::where('id', $identifier)->orWhere('account_no', $identifier)->first();

        if (! $customer) {
            throw new \InvalidArgumentException('Customer not found');
        }

        $params = [
            'Amount' => intval($amount),
            'AccountReference' => $customer->id_no,
            'PartyA' => $customer->phone_no,
            'PhoneNumber' => $customer->phone_no,
        ];

        $response = Mpesa::stkPush($params);
        Log::channel('mpesa')->info('MPESA StkPush Request Response: '.$response);

        return ['status' => 'success'];
    }

    public function initiateStkLoad(
        string $identifier,
        float $amount,
        string $type,
        ?float $coinValue = null,
        ?string $phoneNo = null,
        ?string $referralCode = null,
    ): array {
        $customer = Customer::where('id', $identifier)->orWhere('account_no', $identifier)->first();

        if (! $customer) {
            throw new \InvalidArgumentException('Customer not found');
        }

        $billRefNo = match ($type) {
            'load' => $customer->account_no.'#'.$coinValue.'#load',
            'gift' => $customer->account_no.'#'.$amount.'#gift',
            'emoji' => $customer->account_no.'#'.$amount.'#emoji',
            default => $customer->account_no,
        };

        if ($referralCode) {
            $billRefNo .= '#'.$referralCode;
        } elseif (! empty($customer->referral_code)) {
            $billRefNo .= '#'.$customer->referral_code;
        }

        $params = [
            'Amount' => (float) $amount,
            'AccountReference' => $billRefNo,
            'PartyA' => $phoneNo ?? $customer->phone_no,
            'PhoneNumber' => $phoneNo ?? $customer->phone_no,
        ];

        $response = Mpesa::stkPush($params);
        Log::channel('mpesa')->info('MPESA StkPush Load Request Response: '.$response);

        return ['status' => 'success'];
    }
}
