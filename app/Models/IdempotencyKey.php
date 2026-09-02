<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'idempotency_key',
        'user_id',
        'endpoint',
        'request_hash',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected $casts = [
        'response_status' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
