<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-wal-txn-key');
});

it('lists wallet transactions', function () {
    $response = $this->getJson('/api/v1/wallets/transactions', apiHeaders($this->apiKey->key));

    $response->assertOk();
});

it('shows daily amount', function () {
    $response = $this->getJson('/api/v1/wallets/today', apiHeaders($this->apiKey->key));

    $response->assertOk();
});
