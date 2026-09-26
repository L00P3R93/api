<?php

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\DepositResolution;
use App\Models\ExciseDutyCharge;
use App\Models\HouseFunding;
use App\Models\LedgerEntry;
use App\Models\PromoCode;
use App\Models\PromotionCredit;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->houseCustomer = Customer::factory()->create(['account_no' => '10000001']);
    $this->houseWallet = Wallet::factory()->create(['customer_id' => $this->houseCustomer->id, 'balance' => 0]);

    config([
        'wallets.house_wallet_id' => $this->houseWallet->id,
        'finance.test_customer_ids' => [],
        'finance.excise_duty.enabled' => true,
        'finance.excise_duty.rate' => 0.05,
        'finance.excise_duty.effective_from' => null,
        'referrals.enabled' => true,
        'referrals.effective_from' => null,
    ]);

    $this->apiKey = createApiKey('test-house-funding-key');
    $this->headers = apiHeaders($this->apiKey->key);
});

/**
 * A C2B payment to the paybill; with the default bill ref it matches no customer and is left unmatched.
 */
function housePayment(float $amount = 10000, string $billRef = 'HOUSE', string $transId = 'HSE0000001'): Deposit
{
    test()->postJson('/api/v1/c2b/confirm', [
        'TransID' => $transId,
        'TransactionType' => 'Pay Bill',
        'TransTime' => now()->format('YmdHis'),
        'TransAmount' => $amount,
        'BusinessShortCode' => '4007279',
        'BillRefNumber' => $billRef,
        'MSISDN' => '254700000001',
        'FirstName' => 'Owner',
    ]);

    return Deposit::where('trans_id', $transId)->sole();
}

function fundHouse(Deposit $deposit, string $note = 'Owner top-up for signup bonuses')
{
    return test()->postJson('/api/v1/deposits/'.encryptId($deposit->id).'/house-funding', ['note' => $note], test()->headers);
}

function voidHouseFunding(int $id, string $reason = 'Customer payment, not owner money')
{
    return test()->postJson('/api/v1/finance/house-funding/'.encryptId($id).'/void', ['reason' => $reason], test()->headers);
}

// funding

it('credits the full deposit to the house wallet as capital, with no excise duty', function () {
    $deposit = housePayment(10000);

    fundHouse($deposit)
        ->assertOk()
        ->assertJsonPath('data.deposit.status', Deposit::STATUS_COMPLETED)
        ->assertJsonPath('data.deposit.resolution.action', 'house_funded')
        ->assertJsonPath('data.house_funding.amount', 10000)
        ->assertJsonPath('data.house_funding.trans_id', 'HSE0000001')
        ->assertJsonPath('data.house_funding.status', 'active')
        ->assertJsonPath('data.house_funding.recorded_by', 'api_key:'.$this->apiKey->id);

    $entry = LedgerEntry::where('wallet_id', $this->houseWallet->id)->sole();

    expect((float) $this->houseWallet->fresh()->balance)->toBe(10000.0)
        ->and($entry->entry_type)->toBe('house_funding')
        ->and((float) $entry->credit)->toBe(10000.0)
        ->and($entry->referenceable_id)->toBe($deposit->id)
        ->and(ExciseDutyCharge::count())->toBe(0)
        ->and(Transaction::where('payment_id', $deposit->id)->count())->toBe(0)
        ->and(HouseFunding::sole()->ledger_entry_id)->toBe($entry->id);
});

it('does not touch referrals even when the house customer was referred', function () {
    Referral::create(['referrer_id' => Customer::factory()->create()->id, 'referred_id' => $this->houseCustomer->id, 'code_used' => 'CODE1234', 'verified_at' => now()]);

    fundHouse(housePayment())->assertOk();

    expect(Referral::sole()->first_deposit_id)->toBeNull();
});

it('refuses a deposit that is not unmatched', function () {
    $deposit = housePayment();
    fundHouse($deposit)->assertOk();

    fundHouse($deposit)
        ->assertStatus(409)
        ->assertJsonPath('code', 'already_resolved');

    expect((float) $this->houseWallet->fresh()->balance)->toBe(10000.0);
});

it('returns 404 for an unknown deposit', function () {
    $this->postJson('/api/v1/deposits/'.encryptId(999999).'/house-funding', ['note' => 'Owner top-up'], $this->headers)->assertNotFound();
});

it('requires a note', function () {
    $this->postJson('/api/v1/deposits/'.encryptId(housePayment()->id).'/house-funding', [], $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');
});

it('lets the house wallet pay signup bonuses once funded', function () {
    config(['promotions.signup_bonus.enabled' => true, 'promotions.signup_bonus.net_amount' => 20, 'promotions.signup_bonus.budget_cap' => null]);
    $customer = Customer::factory()->create(['phone_no' => '254711000009']);
    $customer->forceFill(['promo_code_id' => PromoCode::factory()->create()->id])->save();
    Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 0]);

    fundHouse(housePayment(100))->assertOk();

    $this->postJson('/api/v1/customers/'.encryptId($customer->id).'/verified', [], $this->headers)->assertOk();

    expect(PromotionCredit::where('customer_id', $customer->id)->exists())->toBeTrue()
        ->and((float) $this->houseWallet->fresh()->balance)->toBe(78.95);
});

// the house account itself

it('leaves a paybill payment to the house account number unmatched instead of crediting it as a deposit', function () {
    $deposit = housePayment(5000, '10000001', 'HSE0000002');

    expect((int) $deposit->status)->toBe(Deposit::STATUS_UNMATCHED)
        ->and((float) $this->houseWallet->fresh()->balance)->toBe(0.0)
        ->and(LedgerEntry::count())->toBe(0)
        ->and(ExciseDutyCharge::count())->toBe(0);

    fundHouse($deposit)->assertOk();

    expect((float) $this->houseWallet->fresh()->balance)->toBe(5000.0);
});

it('leaves a direct deposit to the house account unmatched', function () {
    $this->postJson('/api/v1/deposits', [
        'TransID' => 'HSE0000003',
        'TransactionType' => 'Pay Bill',
        'TransTime' => now()->format('YmdHis'),
        'TransAmount' => 5000,
        'BusinessShortCode' => '4007279',
        'BillRefNumber' => '10000001',
        'MSISDN' => '254700000001',
        'FirstName' => 'Owner',
    ], $this->headers)->assertStatus(202);

    expect((int) Deposit::where('trans_id', 'HSE0000003')->sole()->status)->toBe(Deposit::STATUS_UNMATCHED)
        ->and((float) $this->houseWallet->fresh()->balance)->toBe(0.0);
});

it('refuses to assign an unmatched deposit to the house customer', function () {
    $deposit = housePayment();

    $this->postJson('/api/v1/deposits/'.encryptId($deposit->id).'/assign', ['customer_id' => $this->houseCustomer->id, 'note' => 'Owner money'], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'house_customer');

    expect((int) $deposit->fresh()->status)->toBe(Deposit::STATUS_UNMATCHED)
        ->and((float) $this->houseWallet->fresh()->balance)->toBe(0.0);
});

it('never matches a payment to the house account in the account-number matcher', function () {
    housePayment(5000, '10000001', 'HSE0000004');

    $this->postJson('/api/v1/deposits/unmatched/match', ['dry_run' => false], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.matched', 0);

    expect((float) $this->houseWallet->fresh()->balance)->toBe(0.0);
});

// void

it('voids a funding: reverses the credit and puts the deposit back to unmatched', function () {
    $deposit = housePayment(10000);
    $id = fundHouse($deposit)->json('data.house_funding.id');

    voidHouseFunding($id)
        ->assertOk()
        ->assertJsonPath('data.house_funding.status', 'voided')
        ->assertJsonPath('data.house_funding.void_reason', 'Customer payment, not owner money');

    expect((float) $this->houseWallet->fresh()->balance)->toBe(0.0)
        ->and(LedgerEntry::orderBy('id')->pluck('entry_type')->all())->toBe(['house_funding', 'house_funding_reversal'])
        ->and((int) $deposit->fresh()->status)->toBe(Deposit::STATUS_UNMATCHED)
        ->and(DepositResolution::count())->toBe(0);

    $customer = Customer::factory()->create();
    Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 0]);

    $this->postJson('/api/v1/deposits/'.encryptId($deposit->id).'/assign', ['customer_id' => $customer->id, 'note' => 'Their payment'], $this->headers)->assertOk();
});

it('refuses to void when the house wallet has spent the money', function () {
    $id = fundHouse(housePayment(100))->json('data.house_funding.id');
    $this->houseWallet->update(['balance' => 50]);

    voidHouseFunding($id)
        ->assertStatus(409)
        ->assertJsonPath('code', 'insufficient_house_balance');

    expect(HouseFunding::find($id)->isVoided())->toBeFalse();
});

it('refuses to void twice', function () {
    $id = fundHouse(housePayment())->json('data.house_funding.id');
    voidHouseFunding($id)->assertOk();

    voidHouseFunding($id)
        ->assertStatus(409)
        ->assertJsonPath('code', 'already_resolved');

    expect(LedgerEntry::where('entry_type', 'house_funding_reversal')->count())->toBe(1);
});

// reports

it('keeps the trial balance balanced and reconciliation clean', function () {
    fundHouse(housePayment(10000))->assertOk();
    $voided = fundHouse(housePayment(500, 'HOUSE', 'HSE0000005'))->json('data.house_funding.id');
    voidHouseFunding($voided)->assertOk();

    $trial = $this->getJson('/api/v1/finance/trial-balance', $this->headers)->assertOk()->json('data');

    expect($trial['check']['balanced'])->toBeTrue()
        ->and(collect($trial['lines'])->firstWhere('entry_type', 'house_funding'))->toMatchArray(['account' => 'house_wallet', 'category' => 'capital']);

    $checks = collect($this->getJson('/api/v1/finance/reconciliation', $this->headers)->assertOk()->json('data.checks'))->keyBy('key');

    foreach (['house_funding_ledger', 'deposit_resolutions', 'deposits_without_ledger', 'deposits_without_excise_duty', 'customer_wallet_drift'] as $key) {
        expect($checks[$key]['status'])->toBe('pass', $key);
    }
});

it('shows house funding apart from customer deposits in cash flow and the deposits drill-down', function () {
    fundHouse(housePayment(10000))->assertOk();

    $cash = $this->getJson('/api/v1/finance/cash-flow', $this->headers)->assertOk()->json('data.totals.cash_in');

    expect($cash['house_funding'])->toEqual(10000)
        ->and($cash['wallet_deposit'])->toEqual(0);

    $deposits = $this->getJson('/api/v1/finance/deposits', $this->headers)->assertOk()->json('data');

    expect($deposits['items'][0]['kind'])->toBe('house_funding')
        ->and($deposits['summary']['top_depositors'])->toBe([]);
});

it('lists house fundings with totals', function () {
    fundHouse(housePayment(10000))->assertOk();
    $voided = fundHouse(housePayment(500, 'HOUSE', 'HSE0000006'))->json('data.house_funding.id');
    voidHouseFunding($voided)->assertOk();

    $data = $this->getJson('/api/v1/finance/house-funding', $this->headers)->assertOk()->json('data');

    expect($data['items'])->toHaveCount(1)
        ->and($data['items'][0]['amount'])->toEqual(10000)
        ->and($data['summary']['active'])->toEqual(['entries' => 1, 'amount' => 10000])
        ->and($data['summary']['voided'])->toEqual(['entries' => 1, 'amount' => 500])
        ->and($data['summary']['house_wallet_balance'])->toEqual(10000);

    expect($this->getJson('/api/v1/finance/house-funding?status=voided', $this->headers)->json('data.items.0.status'))->toBe('voided');
});

it('lists house-funded deposits among resolved unmatched deposits', function () {
    fundHouse(housePayment())->assertOk();

    $this->getJson('/api/v1/deposits/unmatched?status=house_funded', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.items.0.resolution.action', 'house_funded');
});
