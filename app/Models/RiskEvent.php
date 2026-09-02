<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskEvent extends Model
{
    protected $table = 'risk_events';

    public $timestamps = false;

    protected $fillable = [
        'api_key_id',
        'ip_address',
        'customer_id',
        'event_type',
        'severity',
        'score',
        'details',
        'action_taken',
        'resolved_at',
        'created_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'details' => 'array',
        'created_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
