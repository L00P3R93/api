<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-stk-callback-key');
});

it('processes STK callback', function () {
    $payload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '12345',
                'CheckoutRequestID' => 'ws_CO_12345',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 100],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'QKH00V0FKH'],
                        ['Name' => 'Balance'],
                        ['Name' => 'TransactionDate', 'Value' => 20260901120000],
                        ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/stk/callback', $payload);

    $response->assertStatus(200);
});
