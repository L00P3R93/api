<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralBonusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referral_id' => $this->referral_id,
            'referred_id' => $this->referred_id,
            'referred_name' => $this->referred?->name,
            'milestone' => $this->milestone,
            'amount' => (float) $this->amount,
            'ledger_entry_id' => $this->ledgerEntry?->entry_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
