<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceExpenseRequest extends FormRequest
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
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category' => ['required', Rule::in(config('finance.expense_categories'))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            // A reference (e.g. an M-Pesa receipt) may only be used once, unless the expense using it was voided.
            'reference' => [
                'nullable', 'string', 'max:100',
                Rule::unique('finance_expenses', 'reference')->whereNull('voided_at'),
            ],
        ];
    }
}
