<?php

use App\Models\Customer;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->walletService = app(WalletService::class);
});

it('transfer without receiver fails', function () {
    $sender = Customer::factory()->create();
    $senderWallet = Wallet::factory()->create(['customer_id' => $sender->id, 'balance' => 100]);

    $result = $this->walletService->transfer($senderWallet->id, 30, null);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Receiver wallet is required');
    expect($result['status_code'])->toBe(400);
});

it('transfer to nonexistent receiver fails', function () {
    $sender = Customer::factory()->create();
    $senderWallet = Wallet::factory()->create(['customer_id' => $sender->id, 'balance' => 100]);

    $result = $this->walletService->transfer($senderWallet->id, 30, 9999);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Receiver wallet not found');
    expect($result['status_code'])->toBe(404);
});
