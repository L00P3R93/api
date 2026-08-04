<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchasesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $purchaseArray = [
            'id' => $this->id,
            'purchase_type' => $this->purchase_type,
            'amount' => $this->amount,
            'value' => $this->value,
            'referral_code' => $this->referral_code,
            'created_at' => $this->created_at,
        ];
        $customer = $this->customer;
        if ($customer) {
            $purchaseArray = array_merge($purchaseArray, [
                'name' => $customer->name,
            ]);
        }
        $deposit = $this->deposit;
        if ($deposit) {
            $purchaseArray = array_merge($purchaseArray, [
                'trans_id' => $deposit->trans_id,
            ]);
        }
        return $purchaseArray;
    }
}
