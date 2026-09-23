<?php

namespace App\Http\Resources;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
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
            'complaint_id' => $this->complaint_id,
            'customer_id' => $this->customer_id,
            'subject_type' => $this->subject_type,
            'game_wallet_id' => $this->game_wallet_id,
            'competition_wallet_id' => $this->competition_wallet_id,
            'reason' => $this->reason,
            'description' => $this->description,
            'status' => $this->status,
            'disputed_amount' => (float) $this->disputed_amount,
            'held_amount' => (float) $this->held_amount,
            'shortfall_amount' => (float) $this->shortfall_amount,
            'refunded_amount' => (float) $this->refunded_amount,
            'house_cuts_reversed' => (float) $this->house_cuts_reversed,
            'released_amount' => (float) $this->released_amount,
            'filed_by' => $this->filed_by,
            'resolution_note' => $this->resolution_note,
            'closed_by' => $this->closed_by,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'disputed_transactions' => DisputedTransactionResource::collection($this->whenLoaded('disputedTransactions')),
            'refunds' => $this->whenLoaded('refunds', fn () => $this->refunds->map(fn (LedgerEntry $refund) => [
                'customer_id' => $refund->customer_id,
                'wallet_id' => $refund->wallet_id,
                'amount' => (float) $refund->credit,
            ])->values()),
        ];
    }
}
