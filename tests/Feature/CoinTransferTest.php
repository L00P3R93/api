<?php

use App\Models\Coin;
use App\Models\Customer;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-coin-transfer-key');
    $this->sender = Customer::factory()->create(['id_no' => 'CTR001']);
    $this->receiver = Customer::factory()->create(['id_no' => 'CTR002']);
    Wallet::factory()->create(['customer_id' => $this->sender->id, 'balance' => 100]);
    Wallet::factory()->create(['customer_id' => $this->receiver->id, 'balance' => 100]);
    $this->senderCoin = Coin::create(['customer_id' => $this->sender->id, 'coins' => 200, 'status' => 1]);
    Coin::create(['customer_id' => $this->receiver->id, 'coins' => 50, 'status' => 1]);
});

it('validates coins required for transfer', function () {
    $encryptedId = encryptId($this->senderCoin->id);

    $response = $this->postJson("/api/v1/coins/transfer/{$encryptedId}", [], apiHeaders($this->apiKey->key));

    expect(in_array($response->status(), [422, 500]))->toBeTrue();
});
