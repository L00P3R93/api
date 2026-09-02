<?php

use App\Models\Customer;
use App\Models\GameWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-game-txn-key');
    $this->customer = Customer::factory()->create(['id_no' => 'GTxn001']);
    $this->gameWallet = GameWallet::create([
        'game_id' => 'GAME_TXN_001',
        'game_type' => 1,
        'balance' => 1000,
    ]);
});

it('validates required fields for game bet creation', function () {
    $response = $this->postJson('/api/v1/game/bets', [], apiHeaders($this->apiKey->key));

    $response->assertStatus(422);
});

it('lists game bets', function () {
    $response = $this->getJson('/api/v1/game/bets', apiHeaders($this->apiKey->key));

    $response->assertOk();
});
