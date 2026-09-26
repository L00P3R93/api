<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Signup Bonus
    |--------------------------------------------------------------------------
    |
    | A one-off credit to a new customer's main wallet once they are verified
    | (POST /customers/{id}/verified or /referral/verified). Only customers who
    | signed up with a promo code (promo_code on POST /customers) get it, and
    | both signup and verification must be before the code expires. Codes are
    | managed from the GMS (/promo-codes), each with an optional limit on how
    | many bonuses it pays. The house wallet funds it. When excise duty is on,
    | the credit is grossed up so the customer keeps net_amount after duty
    | (20 net = 21.05 gross at 5%).
    |
    | The net amount is locked: it cannot be withdrawn, transferred or spent
    | on coins until the customer has staked that much in games, tournaments
    | or jackpots. Stakes that are later refunded do not count.
    |
    | enabled         off until finance switches it on. While off, codes are
    |                 not applied at signup and no bonus is granted.
    | net_amount      KES the customer keeps after excise duty.
    | budget_cap      total gross KES (excise included) all signup bonuses may
    |                 cost. Empty for no cap.
    |
    */

    'signup_bonus' => [
        'enabled' => (bool) env('PROMOTIONS_SIGNUP_BONUS_ENABLED', false),
        'net_amount' => (float) env('PROMOTIONS_SIGNUP_BONUS_NET_AMOUNT', 20),
        'budget_cap' => env('PROMOTIONS_SIGNUP_BONUS_BUDGET_CAP') === null || env('PROMOTIONS_SIGNUP_BONUS_BUDGET_CAP') === ''
            ? null
            : (float) env('PROMOTIONS_SIGNUP_BONUS_BUDGET_CAP'),
    ],

];
