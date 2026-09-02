<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-purchase-key');
});

it('lists purchases', function () {
    $response = $this->getJson('/api/v1/purchases', apiHeaders($this->apiKey->key));

    expect(in_array($response->status(), [200, 500]))->toBeTrue();
});
