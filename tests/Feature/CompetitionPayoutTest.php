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

it('pays out a jackpot round like a tournament round', function () {
    [$sender, $receiver, $receiverWallet] = createJackpotWalletPair();

    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => $sender->id,
        'receiver_competition_wallet_id' => $receiver->id,
    ], apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJsonMissing(['withdrawal']);

    $sender->refresh();
    $receiver->refresh();
    expect((float) $sender->balance)->toBe(0.0);
    expect($sender->level)->toBe(1);
    expect((float) $receiver->balance)->toBe(50.0);
    expect($receiver->level)->toBe(3);
    expect($receiver->status)->toBe(1);
    expect((float) $receiverWallet->fresh()->balance)->toBe(0.0);
});

it('withdraws the jackpot receiver funds when receiver_withdraw is true', function () {
    [$sender, $receiver, $receiverWallet] = createJackpotWalletPair();

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

it('pays out a jackpot round whose jp_rounds is not a milestone value', function () {
    [$sender, $receiver] = createJackpotWalletPair();
    $sender->update(['jp_rounds' => 7]);
    $receiver->update(['jp_rounds' => 7]);

    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => $sender->id,
        'receiver_competition_wallet_id' => $receiver->id,
    ], apiHeaders($this->apiKey->key));

    $response->assertOk();
    expect((float) $receiver->fresh()->balance)->toBe(50.0);
});

it('rejects a payout where sender and receiver are the same wallet', function () {
    [$sender] = createTournamentWalletPair();

    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => $sender->id,
        'receiver_competition_wallet_id' => $sender->id,
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(400);
    $response->assertJsonFragment(['Sender and Receiver Competition Wallets must be different']);
    expect((float) $sender->fresh()->balance)->toBe(50.0);
});

it('rejects a payout when the sender wallet is not open', function () {
    [$sender, $receiver] = createTournamentWalletPair();
    $sender->update(['status' => 3]);

    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => $sender->id,
        'receiver_competition_wallet_id' => $receiver->id,
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(400);
    $response->assertJsonFragment(['Sender Competition Wallet is not open']);
    expect((float) $receiver->fresh()->balance)->toBe(0.0);
});

it('rejects a payout when the receiver wallet is not open', function () {
    [$sender, $receiver] = createTournamentWalletPair();
    $receiver->update(['status' => 3]);

    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => $sender->id,
        'receiver_competition_wallet_id' => $receiver->id,
        'receiver_withdraw' => true,
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(400);
    $response->assertJsonFragment(['Receiver Competition Wallet is not open']);
    expect((float) $sender->fresh()->balance)->toBe(50.0);
});
