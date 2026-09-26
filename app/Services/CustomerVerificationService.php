<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PromotionCredit;
use App\Models\Referral;

/**
 * What happens when the client reports a customer as verified (email and phone): the verification is
 * recorded on the customer (phone_no_verified_at), a referral is marked verified and its bonuses paid,
 * and the signup bonus is granted when the customer qualifies. Safe to call more than once.
 */
class CustomerVerificationService
{
    public function __construct(
        private ReferralService $referrals,
        private SignupBonusService $signupBonus,
    ) {}

    /**
     * @return array{customer: Customer, referral: ?Referral, signup_bonus: ?PromotionCredit}
     */
    public function markVerified(Customer $customer): array
    {
        if ($customer->phone_no_verified_at === null) {
            $customer->forceFill(['phone_no_verified_at' => now()])->save();
        }

        return [
            'customer' => $customer,
            'referral' => $this->referrals->markVerified($customer),
            'signup_bonus' => $this->signupBonus->grantIfEligible($customer),
        ];
    }
}
