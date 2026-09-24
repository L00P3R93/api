<?php

namespace App\Http\Resources;

use App\Services\FinanceMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralWithdrawalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'amount' => (float) $this->amount,
            'phone_no' => app(FinanceMasker::class)->phone($this->phone_no),
            'status' => $this->status,
            'mpesa_receipt' => $this->mpesa_receipt,
            'result_code' => $this->result_code,
            'result_desc' => $this->result_desc,
            'ledger_entry_id' => $this->ledgerEntry?->entry_id,
            'settled_by' => $this->settled_by,
            'settlement_note' => $this->settlement_note,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
