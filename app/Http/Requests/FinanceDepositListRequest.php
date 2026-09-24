<?php

namespace App\Http\Requests;

use App\Models\Deposit;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The deposits drill-down: `status` is the numeric deposit status (0 unmatched, 1 pending, 2 processed,
 * 4 refunded), so a label such as `refunded` is refused rather than silently read as 0 (unmatched).
 */
class FinanceDepositListRequest extends FinanceListRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:'.implode(',', [Deposit::STATUS_UNMATCHED, 1, Deposit::STATUS_COMPLETED, Deposit::STATUS_REFUNDED])],
        ] + parent::rules();
    }
}
