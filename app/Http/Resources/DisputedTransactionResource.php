<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DisputedTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_type' => Str::snake(class_basename($this->disputable_type)),
            'transaction_id' => $this->disputable_id,
            'customer_id' => $this->customer_id,
            'source_wallet_type' => $this->source_wallet_type,
            'source_wallet_id' => $this->source_wallet_id,
            'amount' => (float) $this->amount,
            'held_amount' => (float) $this->held_amount,
            'shortfall_amount' => (float) $this->shortfall_amount,
            'balance' => (float) $this->balance,
            'status' => $this->status,
        ];
    }
}
