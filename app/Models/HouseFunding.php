<?php

namespace App\Models;

use Database\Factories\HouseFundingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Owner money paid into the paybill and credited to the house wallet as capital, not as a customer
 * deposit. Never edited or deleted: a wrong one is voided, which reverses the credit and puts the deposit
 * back to unmatched.
 */
class HouseFunding extends Model
{
    /** @use HasFactory<HouseFundingFactory> */
    use HasFactory;

    protected $fillable = [
        'deposit_id',
        'amount',
        'ledger_entry_id',
        'note',
        'recorded_by',
        'voided_at',
        'voided_by',
        'void_reason',
        'void_ledger_entry_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    public function voidLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'void_ledger_entry_id');
    }

    /**
     * Fundings that count: everything not voided.
     *
     * @param  Builder<HouseFunding>  $query
     * @return Builder<HouseFunding>
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
