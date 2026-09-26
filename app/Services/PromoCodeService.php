<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PromoCode;
use Illuminate\Support\Carbon;

/**
 * Promo codes for the signup bonus: created and deactivated from the GMS, checked by the client before
 * signup, and attached to the customer when they sign up with a code that is usable at that moment.
 */
class PromoCodeService
{
    public function __construct(private SignupBonusService $signupBonus) {}

    /**
     * A code a player can sign up with now: the promotion is on, and the code exists, is not deactivated
     * or expired, and has redemptions left.
     */
    public function findUsable(?string $code): ?PromoCode
    {
        if (blank($code) || ! $this->signupBonus->isEnabled()) {
            return null;
        }

        $promoCode = PromoCode::usable()->where('code', PromoCode::normalise($code))->first();

        return $promoCode && ! $promoCode->isFull() ? $promoCode : null;
    }

    /**
     * Record the code on a new customer when it is usable. An unknown or unusable code is ignored, so
     * signup never fails because of it. Returns whether the code was applied.
     */
    public function attachAtSignup(Customer $customer, ?string $code): bool
    {
        $promoCode = $this->findUsable($code);

        if (! $promoCode) {
            return false;
        }

        $customer->forceFill(['promo_code_id' => $promoCode->id])->save();

        return true;
    }

    /**
     * @param  array{code: string, expires_at: string, max_redemptions?: ?int, note?: ?string}  $data
     */
    public function create(array $data, ?string $actor): PromoCode
    {
        return PromoCode::create([
            'code' => PromoCode::normalise($data['code']),
            'expires_at' => Carbon::parse($data['expires_at'], config('app.timezone')),
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $actor,
        ]);
    }

    /**
     * Stop a code from being used for new signups or bonuses. Returns false when it was already deactivated.
     */
    public function deactivate(PromoCode $promoCode, ?string $actor): bool
    {
        if ($promoCode->deactivated_at !== null) {
            return false;
        }

        $promoCode->update(['deactivated_at' => now(), 'deactivated_by' => $actor]);

        return true;
    }

    /**
     * @return array{id: int, code: string, promotion: string, status: string, expires_at: string, max_redemptions: ?int, signups: int, redemptions: int, remaining: ?int, note: ?string, created_by: ?string, created_at: ?string, deactivated_at: ?string, deactivated_by: ?string}
     */
    public function present(PromoCode $promoCode): array
    {
        $redemptions = (int) ($promoCode->credits_count ?? $promoCode->credits()->count());

        return [
            'id' => $promoCode->id,
            'code' => $promoCode->code,
            'promotion' => $promoCode->promotion,
            'status' => $promoCode->status(),
            'expires_at' => $promoCode->expires_at->toIso8601String(),
            'max_redemptions' => $promoCode->max_redemptions,
            'signups' => (int) ($promoCode->customers_count ?? $promoCode->customers()->count()),
            'redemptions' => $redemptions,
            'remaining' => $promoCode->max_redemptions === null ? null : max(0, $promoCode->max_redemptions - $redemptions),
            'note' => $promoCode->note,
            'created_by' => $promoCode->created_by,
            'created_at' => $promoCode->created_at?->toIso8601String(),
            'deactivated_at' => $promoCode->deactivated_at?->toIso8601String(),
            'deactivated_by' => $promoCode->deactivated_by,
        ];
    }
}
