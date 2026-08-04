<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        $walletArr = [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'balance' => $this->balance,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
        
        $customer = $this->customer;
        if ($customer) {
            $walletArr = array_merge($walletArr, [
                'name' => $customer->name,
                'account_no' => $customer->account_no
            ]);
        }
        
        return $walletArr;
    }
}
