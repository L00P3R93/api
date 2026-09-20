<?php

$testCustomerIds = env('FINANCE_TEST_CUSTOMER_IDS');

return [

    /*
    |--------------------------------------------------------------------------
    | Test Customer IDs
    |--------------------------------------------------------------------------
    |
    | Customers listed here are excluded from customer stats and financial
    | reports. Set FINANCE_TEST_CUSTOMER_IDS to a comma separated list to
    | override. The default matches the historic "id below 120" rule, which
    | also covers the house customer (id 1).
    |
    */

    'test_customer_ids' => $testCustomerIds === null || $testCustomerIds === ''
        ? range(1, 500)
        : array_values(array_filter(array_map('intval', explode(',', $testCustomerIds)))),

    /*
    |--------------------------------------------------------------------------
    | Fee Schedule
    |--------------------------------------------------------------------------
    |
    | House cut rates as fractions of the amount they apply to. The game and
    | competition services read these, and finance reports use them to check
    | that the cuts actually taken match the schedule.
    |
    | game_credit      taken from each stake when it is credited to a game wallet
    | game_withdrawal  taken from the pool when a game is paid out (rounded up)
    | game_drop        taken from a dropped player's stake pool (rounded up)
    | tournament       taken from each tournament entry (competition game_type 1)
    | jackpot          taken from each jackpot entry (competition game_type 2)
    |
    */

    'fees' => [
        'game_credit' => 0.05,
        'game_withdrawal' => 0.05,
        'game_drop' => 0.10,
        'tournament' => 0.10,
        'jackpot' => 0.20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Coin Exchange Rate
    |--------------------------------------------------------------------------
    |
    | Value of one coin in KES.
    |
    */

    'coin_rate' => 0.04,

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    |
    | max_range_days  longest date range a single report request may cover.
    | cache_ttl       seconds to cache report windows that include today.
    |
    */

    'reports' => [
        'max_range_days' => 366,
        'cache_ttl' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily Snapshot
    |--------------------------------------------------------------------------
    |
    | Time (in the app timezone) the `finance:snapshot` command runs each day.
    |
    */

    'snapshot_time' => '23:59',

    /*
    |--------------------------------------------------------------------------
    | Chart of Accounts
    |--------------------------------------------------------------------------
    |
    | accounts       where money sits, by the kind of wallet a ledger entry
    |                points at. The house wallet is split from customer wallets.
    | entry_types    how each ledger entry_type is reported. An entry type ending
    |                in "_reversal" takes the category of the entry it reverses.
    |
    | Categories: cash_in, cash_out, stake, payout, house_revenue, refund,
    | adjustment, transfer, escrow_movement, coin.
    |
    */

    'accounts' => [
        'customer_wallets' => ['label' => 'Customer wallets', 'type' => 'liability'],
        'house_wallet' => ['label' => 'House wallet', 'type' => 'equity'],
        'game_escrow' => ['label' => 'Game wallets (escrow)', 'type' => 'liability'],
        'competition_escrow' => ['label' => 'Competition wallets (escrow)', 'type' => 'liability'],
        'coin_wallets' => ['label' => 'Coin wallets', 'type' => 'liability'],
    ],

    'entry_types' => [
        'deposit' => 'cash_in',
        'withdrawal' => 'cash_out',
        'game_bet' => 'stake',
        'competition_bet' => 'stake',
        'game_payout' => 'payout',
        'competition_payout' => 'payout',
        'house_cut' => 'house_revenue',
        'refund' => 'refund',
        'adjustment' => 'adjustment',
        'wallet_transfer' => 'transfer',
        'escrow_release' => 'escrow_movement',
        'escrow_transfer' => 'escrow_movement',
        'coin_purchase' => 'coin',
        'coin_exchange' => 'coin',
        'coin_transfer' => 'coin',
    ],

];
