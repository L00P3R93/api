<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepositResolutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'action' => $this->action,
            'customer_id' => $this->customer_id,
            'account_no_used' => $this->account_no_used,
            'ledger_entry_id' => $this->ledgerEntry?->entry_id,
            'mpesa_reference' => $this->mpesa_reference,
            'note' => $this->note,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
