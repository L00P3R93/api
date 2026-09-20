<?php

namespace App\Http\Requests;

use App\Services\FinanceDateRange;
use Carbon\CarbonImmutable;
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
            'exclude_test' => ['nullable', 'in:0,1,true,false'],
            'as_of' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
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

    /**
     * @param  int  $defaultDays  Length of the window when `from` is not sent, ending on `to` (or today).
     */
    public function dateRange(int $defaultDays = 1): FinanceDateRange
    {
        $to = $this->input('to');
        $from = $this->input('from');

        if (! $from && $defaultDays > 1) {
            $from = CarbonImmutable::parse($to ?? 'today', config('app.timezone'))->subDays($defaultDays - 1)->toDateString();
        }

        return FinanceDateRange::fromArray([
            'from' => $from,
            'to' => $to,
            'group_by' => $this->input('group_by'),
            'exclude_test' => $this->boolean('exclude_test', true),
        ]);
    }
}
