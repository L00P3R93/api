<?php

namespace App\Http\Requests;

use App\Services\FinanceDateRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FinanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group_by' => ['nullable', 'in:'.implode(',', [
                FinanceDateRange::GROUP_BY_DAY,
                FinanceDateRange::GROUP_BY_WEEK,
                FinanceDateRange::GROUP_BY_MONTH,
            ])],
            'exclude_test' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $maxDays = (int) config('finance.reports.max_range_days');

                if ($this->dateRange()->days() > $maxDays) {
                    $validator->errors()->add('to', "The date range may not be longer than {$maxDays} days.");
                }
            },
        ];
    }

    public function dateRange(): FinanceDateRange
    {
        return FinanceDateRange::fromArray([
            'from' => $this->input('from'),
            'to' => $this->input('to'),
            'group_by' => $this->input('group_by'),
            'exclude_test' => $this->boolean('exclude_test', true),
        ]);
    }
}
