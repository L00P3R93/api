<?php

use App\Models\Customer;
use App\Models\GameTransaction;
use App\Models\GameWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-game-results-key');
    $this->house = Customer::factory()->create(['id' => 1]);
});

/**
 * A game with the given transactions, each as [customer, payment_type, amount].
 *
 * @param  list<array{0: Customer, 1: string, 2: float}>  $transactions
 */
function gameWithTransactions(string $gameId, array $transactions): GameWallet
{
    $game = GameWallet::create(['game_id' => $gameId, 'game_type' => 1, 'balance' => 0]);

    foreach ($transactions as [$customer, $paymentType, $amount]) {
        GameTransaction::create([
            'game_wallet_id' => $game->id,
            'customer_id' => $customer->id,
            'payment_type' => $paymentType,
            'amount' => $amount,
            'status' => 2,
        ]);
    }

    return $game;
}

function participantFor(array $game, Customer $customer): ?array
{
    return collect($game['participants'])->firstWhere('customer_id', $customer->id);
}

it('shows each player of a finished game as a win or a loss', function () {
    [$winner, $loser] = Customer::factory()->count(2)->create();

    gameWithTransactions('GAME-1', [
        [$winner, 'deposit', 50],
        [$loser, 'deposit', 50],
        [$winner, 'payout', 90],
        [$this->house, 'payout', 10],
    ]);

    $response = $this->getJson('/api/v1/game/results', apiHeaders($this->apiKey->key));

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('pagination');

    $game = $response->json('data.0');

    expect($game)->toMatchArray([
        'game_id' => 'GAME-1',
        'players' => 2,
        'total_bet' => 100,
        'customer_id' => $winner->id,
        'name' => $winner->name,
        'amount' => 90,
        'income' => 10,
        'settlement' => 'payout',
    ])
        ->and($game['participants'])->toHaveCount(2)
        ->and(participantFor($game, $winner))->toMatchArray(['stake' => 50, 'amount_won' => 90, 'result' => 'win'])
        ->and(participantFor($game, $loser))->toMatchArray(['stake' => 50, 'amount_won' => 0, 'result' => 'loss'])
        ->and(participantFor($game, $this->house))->toBeNull();
});

it('includes games settled by a dropped connection', function () {
    [$active, $dropped] = Customer::factory()->count(2)->create();

    gameWithTransactions('GAME-DROP', [
        [$active, 'deposit', 50],
        [$dropped, 'deposit', 50],
        [$this->house, 'payout|dropped', 5],
        [$active, 'payout|dropped', 45],
    ]);

    $game = $this->getJson('/api/v1/game/results', apiHeaders($this->apiKey->key))->json('data.0');

    expect($game['settlement'])->toBe('dropped')
        ->and($game['customer_id'])->toBe($active->id)
        ->and($game['income'])->toEqual(5)
        ->and(participantFor($game, $active)['result'])->toBe('win')
        ->and(participantFor($game, $dropped)['result'])->toBe('loss');
});

it('marks players of a fully refunded game as refunded', function () {
    [$first, $second] = Customer::factory()->count(2)->create();

    gameWithTransactions('GAME-REFUND', [
        [$first, 'deposit', 50],
        [$second, 'deposit', 50],
        [$first, 'refund|full', 50],
        [$second, 'refund|full', 50],
    ]);

    $game = $this->getJson('/api/v1/game/results', apiHeaders($this->apiKey->key))->json('data.0');

    expect($game['settlement'])->toBe('refunded')
        ->and($game['customer_id'])->toBeNull()
        ->and(participantFor($game, $first))->toMatchArray(['amount_refunded' => 50, 'result' => 'refunded']);
});

it('shows a player refunded before the start of a game that was then won', function () {
    [$winner, $loser, $dropped] = Customer::factory()->count(3)->create();

    gameWithTransactions('GAME-MIXED', [
        [$winner, 'deposit', 50],
        [$loser, 'deposit', 50],
        [$dropped, 'deposit', 50],
        [$dropped, 'refund|dropped', 50],
        [$winner, 'payout', 90],
    ]);

    $game = $this->getJson('/api/v1/game/results', apiHeaders($this->apiKey->key))->json('data.0');

    expect(participantFor($game, $winner)['result'])->toBe('win')
        ->and(participantFor($game, $loser)['result'])->toBe('loss')
        ->and(participantFor($game, $dropped)['result'])->toBe('refunded');
});

it('leaves out games that are still being played', function () {
    $player = Customer::factory()->create();

    gameWithTransactions('GAME-OPEN', [[$player, 'deposit', 50]]);
    gameWithTransactions('GAME-OPEN-DROP', [[$player, 'deposit', 50], [$player, 'refund|dropped', 50]]);

    $this->getJson('/api/v1/game/results', apiHeaders($this->apiKey->key))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('pages results newest first when per_page is sent', function () {
    $player = Customer::factory()->create();

    foreach (['GAME-A', 'GAME-B', 'GAME-C'] as $gameId) {
        gameWithTransactions($gameId, [[$player, 'deposit', 50], [$player, 'payout', 45]]);
    }

    $response = $this->getJson('/api/v1/game/results?per_page=2&page=1', apiHeaders($this->apiKey->key));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.game_id', 'GAME-C')
        ->assertJsonPath('data.1.game_id', 'GAME-B')
        ->assertJsonPath('pagination', ['page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);

    $this->getJson('/api/v1/game/results?per_page=2&page=2', apiHeaders($this->apiKey->key))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.game_id', 'GAME-A');
});

it('rejects an invalid per_page', function () {
    $this->getJson('/api/v1/game/results?per_page=500', apiHeaders($this->apiKey->key))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});
