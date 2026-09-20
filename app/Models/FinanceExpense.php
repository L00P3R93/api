<?php

namespace App\Models;

use Database\Factories\FinanceExpenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceExpense extends Model
{
    /** @use HasFactory<FinanceExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'expense_date',
        'category',
        'amount',
        'description',
        'reference',
        'entered_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Expenses that count towards totals: everything not voided.
     *
     * @param  Builder<FinanceExpense>  $query
     * @return Builder<FinanceExpense>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
