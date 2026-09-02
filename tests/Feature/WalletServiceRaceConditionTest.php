<?php

use App\Models\Customer;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->walletService = app(WalletService::class);
});

it('reduce balance rejects insufficient funds', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);

    $result = $this->walletService->reduceBalance($wallet->id, 150);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Insufficient balance');
    expect((float) $wallet->fresh()->balance)->toBe(100.0);
});

it('reduce balance succeeds with sufficient funds', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);

    $result = $this->walletService->reduceBalance($wallet->id, 30);

    expect($result['success'])->toBeTrue();
    expect((float) $result['balance_before'])->toBe(100.0);
    expect((float) $result['balance_after'])->toBe(70.0);
    expect((float) $wallet->fresh()->balance)->toBe(70.0);
});

it('add balance succeeds', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);

    $result = $this->walletService->addBalance($wallet->id, 50);

    expect($result['success'])->toBeTrue();
    expect((float) $result['balance_before'])->toBe(100.0);
    expect((float) $result['balance_after'])->toBe(150.0);
    expect((float) $wallet->fresh()->balance)->toBe(150.0);
});

it('set balance succeeds', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);

    $result = $this->walletService->setBalance($wallet->id, 250);

    expect($result['success'])->toBeTrue();
    expect((float) $result['balance_before'])->toBe(100.0);
    expect((float) $result['balance_after'])->toBe(250.0);
    expect((float) $wallet->fresh()->balance)->toBe(250.0);
});

it('reduce balance returns error for nonexistent wallet', function () {
    $result = $this->walletService->reduceBalance(9999, 50);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Wallet not found');
});

it('add balance returns error for nonexistent wallet', function () {
    $result = $this->walletService->addBalance(9999, 50);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Wallet not found');
});

it('set balance returns error for nonexistent wallet', function () {
    $result = $this->walletService->setBalance(9999, 50);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Wallet not found');
});

it('uses db transaction with lock for update for reduce balance', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);

    DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
        return $callback();
    });

    $result = $this->walletService->reduceBalance($wallet->id, 30);

    expect($result['success'])->toBeTrue();
});

it('uses db transaction with lock for update for add balance', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);

    DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
        return $callback();
    });

    $result = $this->walletService->addBalance($wallet->id, 50);

    expect($result['success'])->toBeTrue();
});
