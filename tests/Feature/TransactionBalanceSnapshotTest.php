<?php

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('transaction model accepts balance_before and balance_after in fillable', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);

    $transaction = Transaction::create([
        'wallet_id' => $wallet->id,
        'payment_ref' => 'TEST_REF_001',
        'payment_type' => 'App\\Models\\Deposit',
        'amount' => 50,
        'status' => 2,
        'balance_before' => 100.0,
        'balance_after' => 150.0,
    ]);

    expect((float) $transaction->balance_before)->toBe(100.0);
    expect((float) $transaction->balance_after)->toBe(150.0);
});

it('transaction balance snapshots are persisted after deposit', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 0]);

    $transaction = $wallet->transactions()->create([
        'payment_ref' => 'TEST_DEP_001',
        'payment_type' => 'App\\Models\\Deposit',
        'amount' => 100,
        'status' => 2,
        'balance_before' => 0.0,
        'balance_after' => 100.0,
    ]);

    $saved = Transaction::find($transaction->id);

    expect((float) $saved->balance_before)->toBe(0.0);
    expect((float) $saved->balance_after)->toBe(100.0);
});

it('transaction balance snapshots are persisted after withdrawal', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);

    $transaction = $wallet->transactions()->create([
        'payment_ref' => 'TEST_WTH_001',
        'payment_type' => 'App\\Models\\Withdraw',
        'amount' => 40,
        'status' => 2,
        'balance_before' => 100.0,
        'balance_after' => 60.0,
    ]);

    $saved = Transaction::find($transaction->id);

    expect((float) $saved->balance_before)->toBe(100.0);
    expect((float) $saved->balance_after)->toBe(60.0);
});
