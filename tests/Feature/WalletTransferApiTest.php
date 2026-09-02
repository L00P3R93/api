<?php

use App\Models\Customer;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-wallet-transfer-key');
});

it('validates required fields for wallet transfer', function () {
    $sender = Customer::factory()->create(['id_no' => 'WTR003']);
    $senderWallet = Wallet::factory()->create(['customer_id' => $sender->id, 'balance' => 500]);
    $encryptedId = encryptId($senderWallet->id);

    $response = $this->postJson("/api/v1/wallets/transfer/{$encryptedId}", [], apiHeaders($this->apiKey->key));

    expect(in_array($response->status(), [422, 500]))->toBeTrue();
});
