<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-b2c-key');
});

it('returns B2C balance data', function () {
    $response = $this->getJson('/api/v1/b2c/balance', apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('processes B2C result callback', function () {
    $payload = [
        'ResultCode' => 0,
        'ResultDesc' => 'The service request is processed successfully.',
        'ConversationID' => 'AG_20260901_12345',
        'OriginatorConversationID' => '12345',
        'TransactionID' => 'LGR33A0B1K',
        'TransactionAmount' => '100',
        'ReceiverPartyIdentifier' => '254712345678',
        'ReceiverIdentifierType' => '1',
        'ResultParameters' => [
            'ResultParameter' => [
                ['Key' => 'TransactionReceipt', 'Value' => 'LGR33A0B1K'],
                ['Key' => 'TransactionAmount', 'Value' => '100'],
            ],
        ],
        'TransactionReceiptDetails' => [
            'ReceiptNo' => 'LGR33A0B1K',
        ],
    ];

    $response = $this->postJson('/api/v1/b2c/result', $payload);

    $response->assertStatus(200);
});

it('processes B2C timeout callback', function () {
    $payload = [
        'ResultCode' => 1,
        'ResultDesc' => 'The service request timed out.',
        'ConversationID' => 'AG_20260901_12345',
        'OriginatorConversationID' => '12345',
        'TransactionID' => 'LGR33A0B1K',
    ];

    $response = $this->postJson('/api/v1/b2c/timeout', $payload);

    $response->assertStatus(200);
});

it('processes B2C balance result callback', function () {
    $payload = [
        'ResultCode' => 0,
        'ResultDesc' => 'The service request is processed successfully.',
        'ConversationID' => 'AG_20260901_12345',
        'OriginatorConversationID' => '12345',
        'TransactionID' => 'LGR33A0B1K',
        'ResultParameters' => [
            'ResultParameter' => [
                ['Key' => 'CurrentBalance', 'Value' => '10000'],
                ['Key' => 'AvailableBalance', 'Value' => '10000'],
                ['Key' => 'AccountBalance', 'Value' => '10000'],
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/b2c/balance/result', $payload);

    $response->assertStatus(200);
});
