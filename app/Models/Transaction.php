<?php

namespace App\Models;

use App\Util\Badge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'wallet_id',
        'payment_id',
        'payment_ref',
        'payment_type',
        'amount',
        'status',
    ];

    public function wallet(){
        return $this->belongsTo(Wallet::class);
    }

    // Define polymorphic relationship
    public function payment(){
        return $this->morphTo();
    }

    public function getPaymentType(): string {
        if(str_contains($this->payment_type, 'Deposit')) {
            return Badge::set('primary bg-gradient-navy', 'Deposit');
        } elseif(str_contains($this->payment_type, 'Withdraw')) {
            return Badge::set('warning bg-gradient-orange', 'Withdraw');
        } else return Badge::set('secondary', 'Unknown');
    }
}
