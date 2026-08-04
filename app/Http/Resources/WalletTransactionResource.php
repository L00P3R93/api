<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->receiver_id,
            'initiator_id' => $this->initiator_id,
            'amount' => $this->amount,
            'status' => $this->status,
        ];
    }
}
