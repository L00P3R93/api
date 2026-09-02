<?php

use App\Models\Customer;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('wallet transfer returns error message on insufficient balance', function () {
    $sender = Customer::factory()->create();
    $receiver = Customer::factory()->create();
    $senderWallet = Wallet::factory()->create(['customer_id' => $sender->id, 'balance' => 100]);
    $receiverWallet = Wallet::factory()->create(['customer_id' => $receiver->id, 'balance' => 50]);

    $service = app(WalletService::class);
    $result = $service->transfer($senderWallet->id, 200, $receiverWallet->id);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Insufficient balance');
});
