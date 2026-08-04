<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class B2C extends Model
{
    protected $table = 'b2c';

    protected $fillable = [
        'amount',
    ];
}
