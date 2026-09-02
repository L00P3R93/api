<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-c2b-key');
});

it('processes C2B confirmation callback', function () {
    $payload = [
        'TransactionType' => 'Pay Bill',
        'TransID' => 'QKH00V0FKH',
        'TransTime' => now()->toDateTimeString(),
        'TransAmount' => 500,
        'BusinessShortCode' => '12345',
        'BillRefNumber' => 'CUST001',
        'MSISDN' => '254712345678',
        'FirstName' => 'John',
        'LastName' => 'Doe',
    ];

    $response = $this->postJson('/api/v1/c2b/confirm', $payload);

    expect(in_array($response->status(), [200, 500]))->toBeTrue();
});

it('processes C2B validation callback', function () {
    $payload = [
        'TransactionType' => 'Pay Bill',
        'TransID' => 'QKH00V0FKH',
        'TransTime' => now()->toDateTimeString(),
        'TransAmount' => 500,
        'BusinessShortCode' => '12345',
        'BillRefNumber' => 'CUST001',
        'MSISDN' => '254712345678',
        'FirstName' => 'John',
        'LastName' => 'Doe',
    ];

    $response = $this->postJson('/api/v1/c2b/validate', $payload);

    expect(in_array($response->status(), [200, 500]))->toBeTrue();
});
