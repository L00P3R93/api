<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-comp-payout-key');
});

it('competition payout returns error for invalid wallets', function () {
    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => 9999,
        'receiver_competition_wallet_id' => 9998,
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(404);
});
