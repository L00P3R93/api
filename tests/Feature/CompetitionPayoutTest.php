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

it('rejects a non-boolean receiver_withdraw value', function () {
    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => 9999,
        'receiver_competition_wallet_id' => 9998,
        'receiver_withdraw' => 'maybe',
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(422);
});

it('does not withdraw the receiver when receiver_withdraw is omitted', function () {
    [$sender, $receiver, $receiverWallet] = createTournamentWalletPair();

    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => $sender->id,
        'receiver_competition_wallet_id' => $receiver->id,
    ], apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJsonMissing(['withdrawal']);

    $receiver->refresh();
    expect((float) $receiver->balance)->toBe(50.0);
    expect($receiver->status)->toBe(1);
    expect((float) $receiverWallet->fresh()->balance)->toBe(0.0);
});

it('withdraws the receiver funds to their wallet when receiver_withdraw is true', function () {
    [$sender, $receiver, $receiverWallet] = createTournamentWalletPair();

    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => $sender->id,
        'receiver_competition_wallet_id' => $receiver->id,
        'receiver_withdraw' => true,
    ], apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJsonPath('withdrawal.status', 'success');

    $receiver->refresh();
    expect((float) $receiver->balance)->toBe(0.0);
    expect($receiver->status)->toBe(3);
    expect((float) $receiverWallet->fresh()->balance)->toBe(50.0);
});

it('reports a failed withdrawal without failing the payout', function () {
    [$sender, $receiver, $receiverWallet] = createTournamentWalletPair();
    $receiver->update(['status' => 3]);

    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => $sender->id,
        'receiver_competition_wallet_id' => $receiver->id,
        'receiver_withdraw' => true,
    ], apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJsonPath('withdrawal.status', 'failed');
    $response->assertJsonPath('withdrawal.error', 'Competition Wallet is not open for withdrawal');

    $receiver->refresh();
    expect((float) $receiver->balance)->toBe(50.0);
    expect((float) $receiverWallet->fresh()->balance)->toBe(0.0);
});
