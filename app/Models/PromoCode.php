<?php

namespace App\Models;

use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A code a player enters at signup to qualify for a promotion (the signup bonus). Both signup and
 * verification must happen before expires_at. max_redemptions (empty = unlimited) caps how many bonuses
 * the code pays. Codes are stored upper case.
 */
class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DEACTIVATED = 'deactivated';

    protected $fillable = [
        'code',
        'promotion',
        'expires_at',
        'max_redemptions',
        'note',
        'created_by',
        'deactivated_at',
        'deactivated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'max_redemptions' => 'integer',
            'deactivated_at' => 'datetime',
        ];
    }

    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * Codes that are neither deactivated nor expired at $at.
     *
     * @param  Builder<PromoCode>  $query
     * @return Builder<PromoCode>
     */
    public function scopeUsable(Builder $query, ?Carbon $at = null): Builder
    {
        return $query->whereNull('deactivated_at')->where('expires_at', '>', $at ?? now());
    }

    public function status(?Carbon $at = null): string
    {
        return match (true) {
            $this->deactivated_at !== null => self::STATUS_DEACTIVATED,
            $this->expires_at->lte($at ?? now()) => self::STATUS_EXPIRED,
            default => self::STATUS_ACTIVE,
        };
    }

    public function isUsable(?Carbon $at = null): bool
    {
        return $this->status($at) === self::STATUS_ACTIVE;
    }

    public function isFull(): bool
    {
        return $this->max_redemptions !== null && $this->credits()->count() >= $this->max_redemptions;
    }

    public function credits(): HasMany
    {
        return $this->hasMany(PromotionCredit::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
