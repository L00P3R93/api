<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $withdrawArr = [
            'id' => $this->id,
            'status' => $this->status,
            'disburse' => $this->disburse,
            'receipt' => $this->receipt,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
        // Eager load the related transactions and wallet data to avoid multiple queries
        $transaction = $this->transactions()->first(); // Get the first transaction

        if ($transaction) {
            $withdrawArr = array_merge($withdrawArr, [
                'customer' => $transaction->wallet?->customer?->name,
                'amount' => $transaction->amount,
                //'wallet' => $transaction->wallet,
                //'transaction' => $transaction
            ]);
        }
        return $withdrawArr;
    }
}
