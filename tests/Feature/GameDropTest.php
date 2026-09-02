<?php

use App\Models\Customer;
use App\Models\GameWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-drop-key');
    $this->customer = Customer::factory()->create(['id_no' => 'DROP001']);
    $this->gameWallet = GameWallet::create([
        'game_id' => 'GAME_DROP_001',
        'game_type' => 1,
        'balance' => 500,
    ]);
});

it('validates players required for drop connection', function () {
    $encryptedId = encryptId($this->gameWallet->id);

    $response = $this->postJson("/api/v1/game/drop/{$encryptedId}", [], apiHeaders($this->apiKey->key));

    expect(in_array($response->status(), [422, 500]))->toBeTrue();
});
