<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    /** @use HasFactory<\Database\Factories\WalletFactory> */
    use HasFactory;

    protected $table = 'wallets';

    protected $fillable = [
        'customer_id',
        'balance'
    ];

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }

    public function walletTransactions(){
        return $this->hasMany(WalletTransaction::class);
    }
}
