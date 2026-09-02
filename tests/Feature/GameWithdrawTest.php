<?php

use App\Models\GameWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-game-withdraw-key');
});

it('game withdraw returns 404 for nonexistent wallet', function () {
    $encryptedId = encryptId(9999);

    $response = $this->postJson("/api/v1/game/withdraw/{$encryptedId}", [
        'customer_id' => '1',
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(404);
});

it('validates customer_id required for game withdraw', function () {
    $gw = GameWallet::create([
        'game_id' => 'GAME_WD_001',
        'game_type' => 1,
        'balance' => 100,
    ]);
    $encryptedId = encryptId($gw->id);

    $response = $this->postJson("/api/v1/game/withdraw/{$encryptedId}", [], apiHeaders($this->apiKey->key));

    $response->assertStatus(422);
});
