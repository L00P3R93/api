<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PromotionCredit;
use App\Services\CustomerVerificationService;
use App\Services\SignupBonusService;
use Illuminate\Http\JsonResponse;

class CustomerPromotionController extends Controller
{
    public function __construct(private SignupBonusService $signupBonus) {}

    /**
     * The client has verified the customer's email and phone. Call it for every player, referred or not.
     * Records the verification, pays any referral bonus now due and grants the signup bonus when the
     * customer qualifies. Safe to call more than once.
     */
    public function verified(CustomerVerificationService $verification, string $encryptedIdentifier): JsonResponse
    {
        $customer = $this->customer($encryptedIdentifier);

        if (! $customer) {
            return $this->customerNotFound();
        }

        $result = $verification->markVerified($customer);

        return response()->json([
            'success' => true,
            'data' => [
                'verified_at' => $customer->phone_no_verified_at?->toIso8601String(),
                'referred' => $result['referral'] !== null,
                'signup_bonus' => $result['signup_bonus'] ? $this->signupBonus->present($result['signup_bonus']) : null,
            ],
        ]);
    }

    /**
     * The customer's promotions, with how much of each is still locked until staked.
     */
    public function index(string $encryptedIdentifier): JsonResponse
    {
        $customer = $this->customer($encryptedIdentifier);

        if (! $customer) {
            return $this->customerNotFound();
        }

        $credits = PromotionCredit::where('customer_id', $customer->id)->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'locked_amount' => $this->signupBonus->lockedAmount($customer->id),
                'items' => $credits->map(fn (PromotionCredit $credit) => $this->signupBonus->present($credit))->values(),
            ],
        ]);
    }

    private function customer(string $identifier): ?Customer
    {
        return Customer::where('id', $identifier)->orWhere('account_no', $identifier)->first();
    }

    private function customerNotFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
    }
}
