<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdraw extends Model
{
    /** @use HasFactory<\Database\Factories\WithdrawFactory> */
    use HasFactory;

    protected $table = 'outgoing_payments';

    protected $fillable = [
        'transaction_id',
        'amount',
        'status',
        'disburse',
        'receipt',
        'user_id',
        'error_message',
    ];

    // Define polymorphic relationship
    public function transactions(){
        return $this->morphMany(Transaction::class, 'payment');
    }
}
