<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaBalance extends Model
{
    protected $table = 'mpesa_balances';

    protected $fillable = [
        'type',
        'account_name',
        'currency',
        'amount',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_response' => 'array',
        ];
    }
}
