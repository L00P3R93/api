<?php

use App\Models\CompetitionTransaction;
use App\Models\CompetitionWallet;
use App\Models\Customer;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-competition-results-key');
});

function competitionWalletAtLevel(Customer $customer, int $level, int $gameType = 1): CompetitionWallet
{
    return CompetitionWallet::create([
        'competition_id' => 'JP-1',
        'cmp_uid' => (string) fake()->unique()->numberBetween(1000, 999999),
        'game_type' => $gameType,
        'customer_id' => $customer->id,
        'level' => $level,
        'jp_rounds' => 3,
        'balance' => 0,
        'status' => 1,
    ]);
}

function competitionResult(CompetitionWallet $wallet, string $paymentType, int $level, float $amount = 50): CompetitionTransaction
{
    return CompetitionTransaction::create([
        'competition_wallet_id' => $wallet->id,
        'customer_id' => $wallet->customer_id,
        'payment_type' => $paymentType,
        'level' => $level,
        'amount' => $amount,
        'status' => 2,
    ]);
}

/**
 * The house share of a competition bet: a c2w row whose sender_id is the competition wallet id.
 * The migrations point sender_id at wallets, which production data does not follow, so the key is
 * switched off here to store what the bet code stores.
 */
function houseShare(CompetitionWallet $wallet, float $amount): void
{
    Schema::withoutForeignKeyConstraints(fn () => WalletTransaction::create([
        'transaction_type' => 'c2w',
        'sender_id' => $wallet->id,
        'receiver_id' => 1,
        'initiator_id' => null,
        'amount' => $amount,
    ]));
}

it('lists win, loss and payout rows with the level of each transaction', function () {
    $winner = competitionWalletAtLevel(Customer::factory()->create(), 3);
    $loser = competitionWalletAtLevel(Customer::factory()->create(), 0);
    houseShare($winner, 5);
    houseShare($loser, 5);

    competitionResult($loser, 'loss', 1);
    competitionResult($winner, 'win', 1);
    competitionResult($winner, 'payout', 2, 180);
    competitionResult($winner, 'deposit', 0);

    $response = $this->getJson('/api/v1/competition/results/'.encryptId(1), apiHeaders($this->apiKey->key));

    $response->assertOk()->assertJsonCount(3, 'data');

    $rows = collect($response->json('data'));

    expect($rows->pluck('payment_type')->all())->toBe(['loss', 'win', 'payout'])
        ->and($rows->firstWhere('payment_type', 'payout'))->toMatchArray([
            'id' => $winner->id,
            'level' => 2,
            'current_level' => 3,
            'income' => '5.00',
        ])
        ->and($rows->firstWhere('payment_type', 'loss'))->toMatchArray(['level' => 1, 'current_level' => 0]);
});

it('keeps results of wallets without a house share and never repeats rows', function () {
    $withShare = competitionWalletAtLevel(Customer::factory()->create(), 1);
    $withoutShare = competitionWalletAtLevel(Customer::factory()->create(), 1);
    houseShare($withShare, 5);
    houseShare($withShare, 5);

    competitionResult($withShare, 'win', 1);
    competitionResult($withoutShare, 'loss', 1);

    $rows = collect($this->getJson('/api/v1/competition/results/'.encryptId(1), apiHeaders($this->apiKey->key))->json('data'));

    expect($rows)->toHaveCount(2)
        ->and((float) $rows->firstWhere('id', $withShare->id)['income'])->toBe(10.0)
        ->and((float) $rows->firstWhere('id', $withoutShare->id)['income'])->toBe(0.0);
});

it('only lists the requested game type', function () {
    competitionResult(competitionWalletAtLevel(Customer::factory()->create(), 1, 2), 'win', 1);

    $this->getJson('/api/v1/competition/results/'.encryptId(1), apiHeaders($this->apiKey->key))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('keeps results of customers who were deleted, without a name', function () {
    $customer = Customer::factory()->create();
    $wallet = competitionWalletAtLevel($customer, 0);
    competitionResult($wallet, 'loss', 1);

    Schema::withoutForeignKeyConstraints(fn () => DB::table('customers')->where('id', $customer->id)->delete());

    $this->getJson('/api/v1/competition/results/'.encryptId(1), apiHeaders($this->apiKey->key))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.payment_type', 'loss')
        ->assertJsonPath('data.0.name', null);
});
