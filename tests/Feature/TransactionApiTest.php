<?php

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-txn-key');
});

it('lists transactions', function () {
    $response = $this->getJson('/api/v1/transactions', apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('shows a specific transaction', function () {
    $customer = Customer::factory()->create(['id_no' => 'TXN001']);
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);
    $txn = Transaction::create([
        'wallet_id' => $wallet->id,
        'payment_ref' => 'TEST_TXN_001',
        'payment_type' => 'App\\Models\\Deposit',
        'amount' => 100,
        'status' => 2,
        'balance_before' => 0,
        'balance_after' => 100,
    ]);
    $encryptedId = encryptId($txn->id);

    $response = $this->getJson("/api/v1/transactions/{$encryptedId}", apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('returns 404 for nonexistent transaction', function () {
    $encryptedId = encryptId(9999);

    $response = $this->getJson("/api/v1/transactions/{$encryptedId}", apiHeaders($this->apiKey->key));

    $response->assertStatus(404);
});
