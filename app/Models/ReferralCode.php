<?php

namespace App\Models;

use Database\Factories\ReferralCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's own referral code, with the link and QR code the client generated for it.
 */
class ReferralCode extends Model
{
    /** @use HasFactory<ReferralCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'code',
        'link',
        'qr_code',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
