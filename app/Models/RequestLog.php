<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestLog extends Model
{
    protected $table = 'request_logs';

    public $timestamps = false;

    protected $fillable = [
        'api_key_id',
        'ip_address',
        'method',
        'endpoint',
        'status_code',
        'request_hash',
        'response_time_ms',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'created_at' => 'datetime',
    ];
}
