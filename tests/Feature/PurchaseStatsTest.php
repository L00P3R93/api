<?php

use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('purchase stats returns valid data without sql error', function () {
    $customer = Customer::factory()->create();
    $deposit = Deposit::create([
        'trans_id' => 'TEST_PURCH_001',
        'trans_type' => 'Pay Bill',
        'trans_time' => now()->toDateTimeString(),
        'trans_amount' => 500,
        'short_code' => '12345',
        'bill_ref_no' => $customer->id_no,
        'msisdn' => '254712345678',
        'name' => 'Test User',
    ]);

    Purchase::create(['customer_id' => $customer->id, 'deposit_id' => $deposit->id, 'amount' => 100, 'purchase_type' => 'standard', 'created_at' => now()]);
    Purchase::create(['customer_id' => $customer->id, 'deposit_id' => $deposit->id, 'amount' => 200, 'purchase_type' => 'standard', 'created_at' => now()]);

    $apiKey = ApiKey::create(['key' => 'test-api-key-stats', 'name' => 'Test', 'is_active' => true]);

    $response = $this->getJson('/api/v1/stats/purchases', ['X-API-KEY' => $apiKey->key]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'today',
            'week',
            'month',
            'year',
            'total',
        ],
    ]);
});
