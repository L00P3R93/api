<?php

use App\Models\CompetitionTransaction;
use App\Models\CompetitionWallet;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\DisputedTransaction;
use App\Models\GameTransaction;
use App\Models\GameWallet;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Services\CompetitionPayoutService;
use App\Services\CompetitionWalletService;
use App\Services\GameWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['finance.fees.game_withdrawal' => 0.05, 'finance.test_customer_ids' => []]);

    $this->apiKey = createApiKey('test-complaints-key');
    $this->headers = apiHeaders($this->apiKey->key);

    $house = Customer::factory()->create(['id' => 1]);
    Wallet::forceCreate(['id' => 1, 'customer_id' => $house->id, 'balance' => 0]);
});

/**
 * A two-player game of 100 each, paid out to the winner: 200 pool, 10 house cut, 190 to the winner.
 *
 * @return array{game: GameWallet, complainant: Customer, winner: Customer, winnerWallet: Wallet}
 */
function paidOutGame(): array
{
    $complainant = Customer::factory()->create();
    Wallet::factory()->create(['customer_id' => $complainant->id, 'balance' => 0]);
    $winner = Customer::factory()->create();
    $winnerWallet = Wallet::factory()->create(['customer_id' => $winner->id, 'balance' => 0]);

    $game = GameWallet::create(['game_id' => 'GAME-'.fake()->unique()->numberBetween(1, 99999), 'balance' => 200, 'status' => 1]);
    foreach ([$complainant, $winner] as $player) {
        GameTransaction::create(['game_wallet_id' => $game->id, 'customer_id' => $player->id, 'payment_type' => 'deposit', 'amount' => 100, 'status' => 1]);
    }

    app(GameWalletService::class)->processGameWithdrawal($game->id, $winner->id);

    return ['game' => $game, 'complainant' => $complainant, 'winner' => $winner, 'winnerWallet' => $winnerWallet->fresh()];
}

/**
 * A round won by the receiver: the sender's 50 moved into the receiver's competition wallet.
 *
 * @return array{0: CompetitionWallet, 1: CompetitionWallet, 2: Wallet}
 */
function wonCompetitionRound(string $kind = 'tournament'): array
{
    [$sender, $receiver, $receiverWallet] = $kind === 'jackpot' ? createJackpotWalletPair() : createTournamentWalletPair();

    app(CompetitionPayoutService::class)->processPayout($sender->id, $receiver->id);

    return [$sender->fresh(), $receiver->fresh(), $receiverWallet];
}

function fileComplaint(object $test, array $payload)
{
    return $test->postJson('/api/v1/complaints', $payload + ['reason' => 'The winner used a bot'], $test->headers);
}

// filing: games

it('holds the game winner payout in dispute escrow', function () {
    ['game' => $game, 'complainant' => $complainant, 'winner' => $winner, 'winnerWallet' => $winnerWallet] = paidOutGame();
    $payout = GameTransaction::where('customer_id', $winner->id)->where('payment_type', 'payout')->sole();

    $response = fileComplaint($this, ['customer_id' => $complainant->id, 'game_wallet_id' => $game->id]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_dispute')
        ->assertJsonPath('data.subject_type', 'game')
        ->assertJsonPath('data.disputed_amount', 190)
        ->assertJsonPath('data.held_amount', 190)
        ->assertJsonPath('data.shortfall_amount', 0)
        ->assertJsonPath('data.filed_by', 'api_key:'.$this->apiKey->id)
        ->assertJsonPath('data.disputed_transactions.0.transaction_type', 'game_transaction')
        ->assertJsonPath('data.disputed_transactions.0.transaction_id', $payout->id)
        ->assertJsonPath('data.disputed_transactions.0.source_wallet_type', 'wallet')
        ->assertJsonPath('data.disputed_transactions.0.balance', 190)
        ->assertJsonPath('data.disputed_transactions.0.status', 'held');
    expect((float) $winnerWallet->fresh()->balance)->toBe(0.0);
    $this->assertDatabaseHas('ledger_entries', ['entry_type' => 'dispute_hold', 'wallet_type' => 'wallet', 'wallet_id' => $winnerWallet->id, 'debit' => 190]);
    $this->assertDatabaseHas('ledger_entries', ['entry_type' => 'dispute_hold', 'wallet_type' => 'dispute', 'credit' => 190]);
});

it('holds what the winner still has and records the shortfall', function () {
    ['game' => $game, 'complainant' => $complainant, 'winnerWallet' => $winnerWallet] = paidOutGame();
    $winnerWallet->update(['balance' => 50]);

    $response = fileComplaint($this, ['customer_id' => $complainant->id, 'game_wallet_id' => $game->id]);

    $response->assertCreated()
        ->assertJsonPath('data.disputed_amount', 190)
        ->assertJsonPath('data.held_amount', 50)
        ->assertJsonPath('data.shortfall_amount', 140);
    expect((float) $winnerWallet->fresh()->balance)->toBe(0.0);
});

it('records a full shortfall without a ledger entry when the winner has nothing left', function () {
    ['game' => $game, 'complainant' => $complainant, 'winnerWallet' => $winnerWallet] = paidOutGame();
    $winnerWallet->update(['balance' => 0]);

    $response = fileComplaint($this, ['customer_id' => $complainant->id, 'game_wallet_id' => $game->id]);

    $response->assertCreated()->assertJsonPath('data.held_amount', 0)->assertJsonPath('data.shortfall_amount', 190);
    expect((float) $winnerWallet->fresh()->balance)->toBe(0.0);
    $this->assertDatabaseMissing('ledger_entries', ['entry_type' => 'dispute_hold']);
});

it('refuses a complaint from someone who did not play the game', function () {
    ['game' => $game, 'winnerWallet' => $winnerWallet] = paidOutGame();
    $outsider = Customer::factory()->create();

    $response = fileComplaint($this, ['customer_id' => $outsider->id, 'game_wallet_id' => $game->id]);

    $response->assertUnprocessable()->assertJsonPath('message', 'Only a player in this game can complain about it.');
    expect(Complaint::count())->toBe(0);
    expect((float) $winnerWallet->fresh()->balance)->toBe(190.0);
});

it('refuses a complaint about a game with no winner payout', function () {
    $complainant = Customer::factory()->create();
    $game = GameWallet::create(['game_id' => 'GAME-OPEN', 'balance' => 100, 'status' => 1]);
    GameTransaction::create(['game_wallet_id' => $game->id, 'customer_id' => $complainant->id, 'payment_type' => 'deposit', 'amount' => 100, 'status' => 1]);

    $response = fileComplaint($this, ['customer_id' => $complainant->id, 'game_wallet_id' => $game->id]);

    $response->assertUnprocessable()->assertJsonPath('message', 'This game has no winner payout to dispute.');
    expect(Complaint::count())->toBe(0);
});

it('refuses named transactions that are not winner payouts of the game', function () {
    ['game' => $game, 'complainant' => $complainant] = paidOutGame();
    $ownDeposit = GameTransaction::where('customer_id', $complainant->id)->sole();

    $response = fileComplaint($this, ['customer_id' => $complainant->id, 'game_wallet_id' => $game->id, 'transaction_ids' => [$ownDeposit->id]]);

    $response->assertUnprocessable()->assertJsonPath('message', "transaction_ids must be winner payouts in this game. Not found: {$ownDeposit->id}.");
    expect(Complaint::count())->toBe(0);
});

it('allows only one open complaint per transaction', function () {
    ['game' => $game, 'complainant' => $complainant, 'winnerWallet' => $winnerWallet] = paidOutGame();
    fileComplaint($this, ['customer_id' => $complainant->id, 'game_wallet_id' => $game->id])->assertCreated();
    $winnerWallet->update(['balance' => 500]);
    $payoutId = DisputedTransaction::sole()->disputable_id;

    $response = fileComplaint($this, ['customer_id' => $complainant->id, 'game_wallet_id' => $game->id, 'reason' => 'A second complaint']);

    $response->assertConflict()->assertJsonPath('message', "Transaction {$payoutId} is already under an open complaint.");
    expect(Complaint::count())->toBe(1);
    expect((float) $winnerWallet->fresh()->balance)->toBe(500.0);
});

// filing: tournaments and jackpots

it('holds the latest win from an open tournament wallet and freezes it', function () {
    [$sender, $receiver, $receiverWallet] = wonCompetitionRound();
    $win = CompetitionTransaction::where('competition_wallet_id', $receiver->id)->where('payment_type', 'win')->sole();

    $response = fileComplaint($this, ['customer_id' => $sender->customer_id, 'competition_wallet_id' => $receiver->id]);

    $response->assertCreated()
        ->assertJsonPath('data.subject_type', 'tournament')
        ->assertJsonPath('data.held_amount', 50)
        ->assertJsonPath('data.disputed_transactions.0.transaction_type', 'competition_transaction')
        ->assertJsonPath('data.disputed_transactions.0.transaction_id', $win->id)
        ->assertJsonPath('data.disputed_transactions.0.source_wallet_type', 'competition_wallet')
        ->assertJsonPath('data.disputed_transactions.0.source_wallet_id', $receiver->id);
    expect((float) $receiver->fresh()->balance)->toBe(0.0);
    expect((float) $receiverWallet->fresh()->balance)->toBe(0.0);
});

it('refuses to withdraw a competition wallet with a pending complaint', function () {
    [$sender, $receiver, $receiverWallet] = wonCompetitionRound();
    fileComplaint($this, ['customer_id' => $sender->customer_id, 'competition_wallet_id' => $receiver->id])->assertCreated();

    $response = $this->postJson('/api/v1/competition/withdraw/'.encryptId($receiver->id), ['customer_id' => (string) $receiver->customer_id], $this->headers);

    $response->assertBadRequest()->assertJsonPath('status', 'Competition Wallet is frozen by a pending complaint');
    expect($receiver->fresh()->status)->toBe(1);
    expect(CompetitionTransaction::where('payment_type', 'payout')->count())->toBe(0);
});

it('refuses a round payout into a competition wallet with a pending complaint', function () {
    [$sender, $receiver] = wonCompetitionRound();
    fileComplaint($this, ['customer_id' => $sender->customer_id, 'competition_wallet_id' => $receiver->id])->assertCreated();
    $sender->update(['balance' => 30]);

    $response = $this->postJson('/api/v1/competition/payout', [
        'sender_competition_wallet_id' => $sender->id,
        'receiver_competition_wallet_id' => $receiver->id,
    ], $this->headers);

    $response->assertBadRequest()->assertJsonPath('error', 'Competition Wallet is frozen by a pending complaint');
    expect((float) $sender->fresh()->balance)->toBe(30.0);
});

it('holds the payout from the customer wallet once a jackpot wallet is closed', function () {
    [$sender, $receiver, $receiverWallet] = wonCompetitionRound('jackpot');
    app(CompetitionWalletService::class)->processWithdrawal($receiver->id, $receiver->customer_id);

    $response = fileComplaint($this, ['customer_id' => $sender->customer_id, 'competition_wallet_id' => $receiver->id]);

    $response->assertCreated()
        ->assertJsonPath('data.subject_type', 'jackpot')
        ->assertJsonPath('data.held_amount', 50)
        ->assertJsonPath('data.disputed_transactions.0.source_wallet_type', 'wallet')
        ->assertJsonPath('data.disputed_transactions.0.source_wallet_id', $receiverWallet->id);
    expect((float) $receiverWallet->fresh()->balance)->toBe(0.0);
});

it('refuses a complaint about the complainant own competition wallet', function () {
    [, $receiver] = wonCompetitionRound();

    $response = fileComplaint($this, ['customer_id' => $receiver->customer_id, 'competition_wallet_id' => $receiver->id]);

    $response->assertUnprocessable()->assertJsonPath('message', 'A customer cannot complain about their own competition wallet.');
    expect(Complaint::count())->toBe(0);
});

it('refuses a complaint from someone outside the competition', function () {
    [, $receiver] = wonCompetitionRound();
    $outsider = Customer::factory()->create();

    $response = fileComplaint($this, ['customer_id' => $outsider->id, 'competition_wallet_id' => $receiver->id]);

    $response->assertUnprocessable()->assertJsonPath('message', 'Only a player in this competition can complain about it.');
});

// validation and auth

it('requires an api key to file a complaint', function () {
    $response = $this->postJson('/api/v1/complaints', [], ['Accept' => 'application/json']);

    $response->assertUnauthorized();
});

it('requires the complainant, a reason and a subject', function () {
    $response = $this->postJson('/api/v1/complaints', [], $this->headers);

    $response->assertUnprocessable()->assertJsonValidationErrors(['customer_id', 'reason', 'game_wallet_id', 'competition_wallet_id']);
});

it('refuses a complaint about a game and a competition at once', function () {
    ['game' => $game, 'complainant' => $complainant] = paidOutGame();
    [, $receiver] = wonCompetitionRound();

    $response = fileComplaint($this, ['customer_id' => $complainant->id, 'game_wallet_id' => $game->id, 'competition_wallet_id' => $receiver->id]);

    $response->assertUnprocessable()->assertJsonValidationErrors('game_wallet_id');
    expect(Complaint::count())->toBe(0);
});

// reading

it('lists complaints filtered by status, newest first', function () {
    $pending = Complaint::factory()->count(2)->create();
    Complaint::factory()->closed()->create();

    $response = $this->getJson('/api/v1/complaints?status=pending_dispute', $this->headers);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $pending[1]->id)
        ->assertJsonPath('meta.total', 2);
});

it('shows one complaint with its disputed transactions', function () {
    $dispute = DisputedTransaction::factory()->create();

    $response = $this->getJson('/api/v1/complaints/'.encryptId($dispute->complaint_id), $this->headers);

    $response->assertOk()
        ->assertJsonPath('data.id', $dispute->complaint_id)
        ->assertJsonPath('data.disputed_transactions.0.id', $dispute->id);
});

it('returns 404 for an unknown complaint', function () {
    $response = $this->getJson('/api/v1/complaints/'.encryptId(999), $this->headers);

    $response->assertNotFound();
});

// finance

it('reports held money as disputed funds and keeps the ledger balanced', function () {
    ['game' => $game, 'complainant' => $complainant] = paidOutGame();
    fileComplaint($this, ['customer_id' => $complainant->id, 'game_wallet_id' => $game->id])->assertCreated();
    Cache::flush();

    $balanceSheet = $this->getJson('/api/v1/finance/balance-sheet', $this->headers)->assertOk()->json('data');
    $trial = $this->getJson('/api/v1/finance/trial-balance', $this->headers)->assertOk()->json('data');

    expect($balanceSheet['liabilities']['disputed_funds'])->toEqual(190)
        ->and($balanceSheet['liabilities']['customer_wallets'])->toEqual(0);
    expect($trial['check']['balanced'])->toBeTrue();
    expect(collect($trial['lines'])->firstWhere('account', 'disputed_funds'))->toMatchArray(['entry_type' => 'dispute_hold', 'credit' => 190]);
    expect(LedgerEntry::where('wallet_type', 'dispute')->sum('credit'))->toEqual(190);
});
