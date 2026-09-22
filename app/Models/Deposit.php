<?php

namespace App\Models;

use Database\Factories\DepositFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Deposit extends Model
{
    /** @use HasFactory<DepositFactory> */
    use HasFactory;

    protected $table = 'incoming_payments';

    protected $fillable = [
        'trans_id',
        'trans_type',
        'trans_time',
        'trans_amount',
        'short_code',
        'bill_ref_no',
        'msisdn',
        'name',
        'status',
    ];

    // Define polymorphic relationship
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'payment');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function exciseDutyCharge(): HasOne
    {
        return $this->hasOne(ExciseDutyCharge::class);
    }
}
