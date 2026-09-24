<?php

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\FinancialSnapshot;
use App\Models\MpesaBalance;
use App\Models\Referral;
use App\Models\ReferralWallet;
use App\Models\ReferralWithdrawal;
use App\Models\Wallet;
use App\Services\BalanceService;
use App\Services\FinanceSnapshotService;
use App\Services\LedgerService;
use App\Services\MpesaService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'finance.test_customer_ids' => [],
        'finance.excise_duty.enabled' => false,
        'referrals.enabled' => true,
        'referrals.effective_from' => null,
    ]);

    $this->apiKey = createApiKey('test-finance-referrals-key');
    $this->headers = apiHeaders($this->apiKey->key);

    $house = Customer::factory()->create(['id' => 1]);
    Wallet::forceCreate(['id' => 1, 'customer_id' => $house->id, 'balance' => 0]);

    $this->referrer = Customer::factory()->create(['name' => 'Referrer One', 'phone_no' => '254711000001']);
});

/**
 * A verified referral with both milestones paid: KES 20 in the referrer's referral wallet.
 */
function earnReferralBonuses(Customer $referrer): Referral
{
    $referred = Customer::factory()->create(['name' => 'Invited Player']);
    $referral = Referral::create(['referrer_id' => $referrer->id, 'referred_id' => $referred->id, 'code_used' => 'CODE1234']);
    $deposit = Deposit::create([
        'trans_id' => 'REFFIN'.$referred->id,
        'trans_type' => 'Pay Bill',
        'trans_time' => now(),
        'trans_amount' => 100,
        'short_code' => '4007279',
        'bill_ref_no' => 'ACC'.$referred->id,
        'msisdn' => '254700000000',
        'name' => 'Invited Player',
    ]);

    $service = app(ReferralService::class);
    DB::transaction(fn () => $service->recordDeposit($deposit, $referred->id));
    $service->markVerified($referred);

    return $referral;
}

/**
 * A referral withdrawal written the way ReferralWithdrawalService writes it, then settled with the given status.
 */
function referralPayout(Customer $customer, float $amount, string $status): ReferralWithdrawal
{
    $wallet = ReferralWallet::where('customer_id', $customer->id)->firstOrFail();

    $withdrawal = ReferralWithdrawal::create([
        'customer_id' => $customer->id,
        'referral_wallet_id' => $wallet->id,
        'amount' => $amount,
        'phone_no' => '254711000001',
        'status' => ReferralWithdrawal::STATUS_PROCESSING,
    ]);

    $entry = app(LedgerService::class)->recordReferralWithdrawal($withdrawal, $wallet, $amount);
    $withdrawal->update(['ledger_entry_id' => $entry->id]);

    if ($status === ReferralWithdrawal::STATUS_COMPLETED) {
        $withdrawal->update(['status' => $status, 'completed_at' => now(), 'mpesa_receipt' => 'RKX1']);
    } elseif ($status === ReferralWithdrawal::STATUS_FAILED) {
        $reversal = app(LedgerService::class)->reverseReferralWithdrawal($entry, $wallet->fresh());
        $withdrawal->update(['status' => $status, 'failed_at' => now(), 'reversal_entry_id' => $reversal->id]);
    }

    return $withdrawal->fresh();
}

function financeReferralGet(string $path)
{
    return test()->getJson('/api/v1/'.$path, test()->headers)->assertOk()->json('data');
}

// statements

it('books completed referral payouts as the expense and shows earned bonuses as a memo', function () {
    earnReferralBonuses($this->referrer);
    ReferralWallet::where('customer_id', $this->referrer->id)->update(['balance' => 120]);
    referralPayout($this->referrer, 50, ReferralWithdrawal::STATUS_COMPLETED);
    referralPayout($this->referrer, 30, ReferralWithdrawal::STATUS_FAILED);

    $data = financeReferralGet('finance/income-statement');

    expect($data['referral_payouts'])->toEqual(50)
        ->and($data['net_income'])->toEqual(-50)
        ->and($data['expenses'])->toEqual(['tracked' => true, 'total' => 0, 'by_category' => []])
        ->and($data['memo']['referral_bonuses_earned'])->toEqual(['signup' => 10, 'first_deposit' => 10, 'total' => 20])
        ->and($data['series'][0]['referral_payouts'])->toEqual(50);
});

it('shows referral payouts apart in the cash flow and takes paid ones out of net cash', function () {
    earnReferralBonuses($this->referrer);
    ReferralWallet::where('customer_id', $this->referrer->id)->update(['balance' => 200]);
    referralPayout($this->referrer, 60, ReferralWithdrawal::STATUS_COMPLETED);
    referralPayout($this->referrer, 50, ReferralWithdrawal::STATUS_PROCESSING);
    referralPayout($this->referrer, 70, ReferralWithdrawal::STATUS_FAILED);

    $totals = financeReferralGet('finance/cash-flow')['totals'];

    expect($totals['referral_payouts'])->toEqual(['paid' => 60, 'pending' => 50, 'failed' => 70])
        ->and($totals['cash_out'])->toEqual(['paid' => 0, 'pending' => 0, 'failed' => 0])
        ->and($totals['cash_in']['total'])->toEqual(100)
        ->and($totals['net_cash'])->toEqual(40);
});

it('lists unspent referral balances as a liability and snapshots them', function () {
    earnReferralBonuses($this->referrer);

    $sheet = financeReferralGet('finance/balance-sheet');
    expect($sheet['liabilities']['referral_wallets'])->toEqual(20);

    $snapshot = app(FinanceSnapshotService::class)->takeSnapshot();
    expect($snapshot->referral_wallets_total)->toBe('20.00');

    $old = financeReferralGet('finance/balance-sheet?as_of='.$snapshot->snapshot_date->toDateString());
    expect($old['liabilities']['referral_wallets'])->toEqual(20);
});

it('reads an old snapshot without the column as zero', function () {
    $snapshot = FinancialSnapshot::factory()->create(['snapshot_date' => '2026-09-01']);

    expect(financeReferralGet('finance/balance-sheet?as_of=2026-09-01')['liabilities']['referral_wallets'])->toEqual(0);
});

it('keeps the trial balance balanced with bonuses, payouts and reversals', function () {
    earnReferralBonuses($this->referrer);
    ReferralWallet::where('customer_id', $this->referrer->id)->update(['balance' => 120]);
    referralPayout($this->referrer, 50, ReferralWithdrawal::STATUS_COMPLETED);
    referralPayout($this->referrer, 30, ReferralWithdrawal::STATUS_FAILED);

    $trial = financeReferralGet('finance/trial-balance');
    $categories = collect($trial['lines'])->pluck('category', 'entry_type');

    expect($trial['check']['balanced'])->toBeTrue()
        ->and($categories['referral_bonus'])->toBe('referral_bonus')
        ->and($categories['referral_withdrawal'])->toBe('referral_payout')
        ->and($categories['referral_withdrawal_reversal'])->toBe('referral_payout');
});

// drill-downs and exports

it('summarises the programme and lists bonuses and withdrawals', function () {
    earnReferralBonuses($this->referrer);
    ReferralWallet::where('customer_id', $this->referrer->id)->update(['balance' => 100]);
    referralPayout($this->referrer, 60, ReferralWithdrawal::STATUS_COMPLETED);

    $summary = financeReferralGet('finance/referrals');
    expect($summary['bonuses_earned'])->toEqual(['signup' => 10, 'first_deposit' => 10, 'total' => 20, 'count' => 2])
        ->and($summary['payouts'])->toEqual(['paid' => 60, 'pending' => 0, 'failed' => 0])
        ->and($summary['position']['unspent_balances'])->toEqual(40)
        ->and($summary['position']['lifetime_paid_out'])->toEqual(60);

    $bonuses = financeReferralGet('finance/referrals/bonuses?type=signup');
    expect($bonuses['items'])->toHaveCount(1)
        ->and($bonuses['items'][0])->toMatchArray(['customer_id' => $this->referrer->id, 'referrer_name' => 'Referrer One', 'referred_name' => 'Invited Player', 'milestone' => 'signup', 'amount' => 10])
        ->and($bonuses['summary']['by_milestone']['signup'])->toEqual(['bonuses' => 1, 'amount' => 10]);

    $withdrawals = financeReferralGet('finance/referrals/withdrawals');
    expect($withdrawals['items'][0])->toMatchArray(['customer_name' => 'Referrer One', 'phone_no' => '2547****0001', 'amount' => 60, 'status' => 'completed', 'mpesa_receipt' => 'RKX1'])
        ->and($withdrawals['summary']['by_status']['completed'])->toEqual(['withdrawals' => 1, 'amount' => 60]);
});

it('exports referral bonuses and withdrawals as CSV', function () {
    earnReferralBonuses($this->referrer);

    $response = $this->get('/api/v1/finance/export/referral-bonuses', $this->headers)->assertOk();
    $lines = array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $response->streamedContent()))));

    expect($lines[0])->toBe('id,paid_at,referral_id,customer_id,referrer_name,referred_id,referred_name,milestone,amount,ledger_entry_id')
        ->and($lines)->toHaveCount(3);

    $this->get('/api/v1/finance/export/referral-withdrawals', $this->headers)->assertOk();
});

// reconciliation

it('flags referral wallets that drift from the ledger and negative referral balances', function () {
    earnReferralBonuses($this->referrer);
    ReferralWallet::where('customer_id', $this->referrer->id)->update(['balance' => -5]);

    $checks = collect(financeReferralGet('finance/reconciliation')['checks'])->keyBy('key');

    expect($checks['referral_wallet_drift']['status'])->toBe('fail')
        ->and($checks['referral_wallet_drift']['amount'])->toEqual(25)
        ->and($checks['negative_balances']['samples'][0]['kind'])->toBe('referral');
});

it('warns about referral withdrawals left open and fails unreversed failures', function () {
    earnReferralBonuses($this->referrer);
    ReferralWallet::where('customer_id', $this->referrer->id)->update(['balance' => 100]);
    $stuck = referralPayout($this->referrer, 50, ReferralWithdrawal::STATUS_PROCESSING);
    $stuck->forceFill(['created_at' => now()->subDays(2)])->save();
    ReferralWithdrawal::create([
        'customer_id' => $this->referrer->id,
        'referral_wallet_id' => ReferralWallet::where('customer_id', $this->referrer->id)->value('id'),
        'amount' => 20, 'phone_no' => '254711000001', 'status' => ReferralWithdrawal::STATUS_FAILED, 'failed_at' => now(),
    ]);

    $checks = collect(financeReferralGet('finance/reconciliation')['checks'])->keyBy('key');

    expect($checks['stuck_referral_withdrawals'])->toMatchArray(['status' => 'warn', 'count' => 1, 'amount' => 50])
        ->and($checks['failed_referral_withdrawals_not_reversed'])->toMatchArray(['status' => 'fail', 'count' => 1, 'amount' => 20]);
});

it('fails bonuses paid for referrals that are not verified', function () {
    $referral = earnReferralBonuses($this->referrer);
    $referral->update(['verified_at' => null]);

    $check = collect(financeReferralGet('finance/reconciliation')['checks'])->firstWhere('key', 'referral_bonuses_unverified');

    expect($check)->toMatchArray(['status' => 'fail', 'count' => 2, 'amount' => 20]);
});

it('counts unspent referral balances in cash coverage', function () {
    earnReferralBonuses($this->referrer);
    MpesaBalance::create(['type' => 'b2c', 'account_name' => 'Utility Account', 'currency' => 'KES', 'amount' => 10]);

    $check = collect(financeReferralGet('finance/reconciliation')['checks'])->firstWhere('key', 'cash_coverage');

    expect($check['status'])->toBe('fail')
        ->and($check['samples'][0]['owed'])->toEqual(20);
});

// referral shortcode balance

it('stores the referral shortcode balance under its own type', function () {
    $this->postJson('/api/v1/referral/balance/b2c/result', ['Result' => ['ResultParameters' => ['ResultParameter' => [
        ['Key' => 'AccountBalance', 'Value' => 'Working Account|KES|0.00|0.00|0.00|0.00&Utility Account|KES|1500.00|1500.00|0.00|0.00'],
    ]]]])->assertOk();

    expect(MpesaBalance::where('type', 'referral_b2c')->pluck('amount', 'account_name')->all())
        ->toEqual(['Working Account' => '0.00', 'Utility Account' => '1500.00']);

    $accounts = collect(financeReferralGet('finance/balance-sheet')['assets']['cash']['accounts']);
    expect($accounts->firstWhere('type', 'referral_b2c')['amount'] ?? null)->not->toBeNull();
});

it('only fetches the referral shortcode balance when its balance URLs are set', function () {
    $mpesa = Mockery::mock(MpesaService::class);
    $mpesa->shouldReceive('b2cAccountBalance')->twice()->andReturn(['ConversationID' => 'AG_B2C']);
    $mpesa->shouldReceive('c2bAccountBalance')->twice()->andReturn(['ConversationID' => 'AG_C2B']);
    $mpesa->shouldReceive('referralB2cAccountBalance')->once()->andReturn(['ConversationID' => 'AG_REF']);
    app()->instance(MpesaService::class, $mpesa);

    config(['mpesa.referral_b2c.balance_result_url' => null]);
    $this->artisan('mpesa:fetch-balances')->doesntExpectOutputToContain('referral')->assertSuccessful();

    config([
        'mpesa.apps.referral_b2c' => ['consumer_key' => 'k', 'consumer_secret' => 's'],
        'mpesa.referral_b2c' => [
            'initiator_name' => 'kadiapi', 'security_credential' => 'pass', 'short_code' => '4151665', 'default_command_id' => 'BusinessPayment',
            'result_url' => 'https://api.test/r', 'timeout_url' => 'https://api.test/t',
            'balance_result_url' => 'https://api.test/br', 'balance_timeout_url' => 'https://api.test/bt',
        ],
    ]);
    app()->forgetInstance(BalanceService::class);
    $this->artisan('mpesa:fetch-balances')->expectsOutputToContain('Referral B2C balance request accepted')->assertSuccessful();
});
