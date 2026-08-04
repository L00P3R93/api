<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepositResource extends JsonResource{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        $depositArr = [
            'id' => $this->id,
            'trans_id' => $this->trans_id,
            'trans_type' => $this->trans_type,
            'trans_time' => $this->trans_time,
            'trans_amount' => $this->trans_amount,
            'short_code' => $this->short_code,
            'bill_ref_no' => $this->bill_ref_no,
            'msisdn' => $this->msisdn,
            'name' => $this->name,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
        // Eager load the related transactions and wallet data to avoid multiple queries
        $transaction = $this->transactions()->first(); // Get the first transaction

        if ($transaction) {
            $depositArr = array_merge($depositArr, [
                'customer' => $transaction->wallet?->customer?->name,
                //'wallet' => $transaction->wallet,
                //'transaction' => $transaction
            ]);
        }
        return $depositArr;
    }
}
