<?php

use App\Models\Customer;
use App\Models\FinanceExpense;
use App\Models\Wallet;
use App\Services\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00:00', config('app.timezone')));
    config(['finance.test_customer_ids' => [1, 2]]);

    $this->apiKey = createApiKey('test-finance-expenses-key');
    $this->headers = apiHeaders($this->apiKey->key);
    $this->actor = 'api_key:'.$this->apiKey->id;
});

function expensePayload(array $overrides = []): array
{
    return $overrides + [
        'expense_date' => '2026-09-20',
        'category' => 'hosting',
        'amount' => 1500.50,
        'description' => 'Server for September',
        'reference' => 'INV-1001',
    ];
}

function recordExpense(object $test, array $overrides = []): array
{
    return $test->postJson('/api/v1/finance/expenses', expensePayload($overrides), $test->headers)->assertCreated()->json('data');
}

function voidExpense(object $test, int $id, string $reason = 'entered in error')
{
    return $test->postJson('/api/v1/finance/expenses/'.encryptId($id).'/void', ['reason' => $reason], $test->headers);
}

function houseRevenue(float $amount): void
{
    $customer = Customer::factory()->create(['id' => 1]);
    $house = Wallet::forceCreate(['id' => 1, 'customer_id' => $customer->id, 'balance' => 0]);
    app(LedgerService::class)->recordHouseCut($house, $amount, 'game_credit');
}

// recording

it('records an expense with who entered it', function () {
    $data = recordExpense($this);

    expect($data)->toMatchArray([
        'expense_date' => '2026-09-20', 'category' => 'hosting', 'amount' => 1500.5, 'description' => 'Server for September',
        'reference' => 'INV-1001', 'status' => 'active', 'entered_by' => $this->actor, 'voided_at' => null,
    ]);
    expect(FinanceExpense::count())->toBe(1);
});

it('rejects an invalid expense', function (array $overrides, string $field) {
    $this->postJson('/api/v1/finance/expenses', expensePayload($overrides), $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
    expect(FinanceExpense::count())->toBe(0);
})->with([
    'unknown category' => [['category' => 'yachts'], 'category'],
    'zero amount' => [['amount' => 0], 'amount'],
    'negative amount' => [['amount' => -5], 'amount'],
    'amount too large' => [['amount' => 1000000000], 'amount'],
    'future date' => [['expense_date' => '2026-09-21'], 'expense_date'],
    'bad date format' => [['expense_date' => '20/09/2026'], 'expense_date'],
    'missing date' => [['expense_date' => null], 'expense_date'],
    'description too long' => [['description' => str_repeat('a', 256)], 'description'],
]);

it('accepts every configured category', function () {
    foreach (config('finance.expense_categories') as $index => $category) {
        recordExpense($this, ['category' => $category, 'reference' => "REF-{$index}"]);
    }

    expect(FinanceExpense::count())->toBe(count(config('finance.expense_categories')));
});

it('allows a reference only once among active expenses', function () {
    $first = recordExpense($this, ['reference' => 'RECEIPT-9']);

    $this->postJson('/api/v1/finance/expenses', expensePayload(['reference' => 'RECEIPT-9', 'amount' => 20]), $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('reference');

    voidExpense($this, $first['id'])->assertOk();

    // a different amount, so the idempotency middleware does not replay the earlier rejected request
    $this->postJson('/api/v1/finance/expenses', expensePayload(['reference' => 'RECEIPT-9', 'amount' => 21]), $this->headers)->assertCreated();
});

it('does not record the same request twice', function () {
    $key = ['Idempotency-Key' => 'expense-once-1'] + $this->headers;

    $this->postJson('/api/v1/finance/expenses', expensePayload(['reference' => null]), $key)->assertCreated();
    $this->postJson('/api/v1/finance/expenses', expensePayload(['reference' => null]), $key);

    expect(FinanceExpense::count())->toBe(1);
});

it('requires an API key to record, void or list expenses', function () {
    $this->postJson('/api/v1/finance/expenses', expensePayload())->assertUnauthorized();
    $this->postJson('/api/v1/finance/expenses/'.encryptId(1).'/void', ['reason' => 'x'])->assertUnauthorized();
    $this->getJson('/api/v1/finance/expenses')->assertUnauthorized();
    $this->getJson('/api/v1/finance/taxes')->assertUnauthorized();
});

// voiding

it('voids an expense, keeps the record and says who and why', function () {
    $expense = recordExpense($this);

    $data = voidExpense($this, $expense['id'], 'wrong amount')->assertOk()->json('data');

    expect($data)->toMatchArray(['status' => 'voided', 'voided_by' => $this->actor, 'void_reason' => 'wrong amount']);
    expect($data['voided_at'])->not->toBeNull();

    $stored = FinanceExpense::find($expense['id']);
    expect($stored->isVoided())->toBeTrue();
    expect((float) $stored->amount)->toBe(1500.5);
});

it('needs a reason to void', function () {
    $expense = recordExpense($this);

    $this->postJson('/api/v1/finance/expenses/'.encryptId($expense['id']).'/void', [], $this->headers)->assertStatus(422)->assertJsonValidationErrors('reason');
    $this->postJson('/api/v1/finance/expenses/'.encryptId($expense['id']).'/void', ['reason' => 'x'], $this->headers)->assertStatus(422);

    expect(FinanceExpense::find($expense['id'])->isVoided())->toBeFalse();
});

it('cannot void an unknown expense or one already voided', function () {
    voidExpense($this, 424242)->assertNotFound();

    $expense = recordExpense($this);
    voidExpense($this, $expense['id'], 'first reason')->assertOk();
    voidExpense($this, $expense['id'], 'second reason')->assertStatus(409);

    expect(FinanceExpense::find($expense['id'])->void_reason)->toBe('first reason');
});

// listing

it('lists active expenses by default with totals and the allowed categories', function () {
    recordExpense($this, ['category' => 'hosting', 'amount' => 1000, 'reference' => 'A']);
    recordExpense($this, ['category' => 'sms', 'amount' => 250, 'reference' => 'B']);
    $wrong = recordExpense($this, ['category' => 'sms', 'amount' => 999, 'reference' => 'C']);
    voidExpense($this, $wrong['id'])->assertOk();

    $data = $this->getJson('/api/v1/finance/expenses', $this->headers)->assertOk()->json('data');

    expect($data['pagination']['total'])->toBe(2);
    expect(array_column($data['items'], 'status'))->toBe(['active', 'active']);
    expect($data['summary']['active'])->toEqual(['entries' => 2, 'amount' => 1250]);
    expect($data['summary']['voided'])->toEqual(['entries' => 1, 'amount' => 999]);
    expect($data['summary']['by_category'])->toEqual(['hosting' => 1000, 'sms' => 250]);
    expect($data['summary']['categories'])->toBe(config('finance.expense_categories'));
});

it('filters expenses by status, category and expense date', function () {
    recordExpense($this, ['expense_date' => '2026-09-20', 'category' => 'hosting', 'reference' => 'A']);
    recordExpense($this, ['expense_date' => '2026-09-10', 'category' => 'sms', 'reference' => 'B']);
    $voided = recordExpense($this, ['expense_date' => '2026-09-20', 'category' => 'sms', 'reference' => 'C']);
    voidExpense($this, $voided['id'])->assertOk();

    $total = fn (string $query) => $this->getJson("/api/v1/finance/expenses?{$query}", $this->headers)->assertOk()->json('data.pagination.total');

    expect($total(''))->toBe(1);
    expect($total('status=all'))->toBe(2);
    expect($total('status=voided'))->toBe(1);
    expect($total('from=2026-09-01&to=2026-09-20'))->toBe(2);
    expect($total('from=2026-09-01&to=2026-09-20&category=sms'))->toBe(1);
    expect($total('from=2026-09-01&to=2026-09-20&status=all&category=sms'))->toBe(2);
});

it('lists the newest expense first and pages the list', function () {
    foreach (range(1, 3) as $n) {
        recordExpense($this, ['expense_date' => "2026-09-1{$n}", 'reference' => "P{$n}"]);
    }

    $page = $this->getJson('/api/v1/finance/expenses?from=2026-09-01&to=2026-09-20&per_page=2&page=1', $this->headers)->json('data');

    expect(array_column($page['items'], 'reference'))->toBe(['P3', 'P2']);
    expect($page['pagination'])->toMatchArray(['total' => 3, 'last_page' => 2]);
});

// income statement

it('takes expenses off revenue in the income statement', function () {
    houseRevenue(400);
    recordExpense($this, ['category' => 'hosting', 'amount' => 100, 'reference' => 'A']);
    recordExpense($this, ['category' => 'hosting', 'amount' => 50, 'reference' => 'B']);
    recordExpense($this, ['category' => 'mpesa_charges', 'amount' => 30, 'reference' => 'C']);
    $voided = recordExpense($this, ['category' => 'sms', 'amount' => 999, 'reference' => 'D']);
    voidExpense($this, $voided['id'])->assertOk();
    recordExpense($this, ['expense_date' => '2026-09-10', 'category' => 'salaries', 'amount' => 700, 'reference' => 'E']);

    $data = $this->getJson('/api/v1/finance/income-statement', $this->headers)->assertOk()->json('data');

    expect($data['revenue']['total'])->toEqual(400);
    expect($data['expenses']['tracked'])->toBeTrue();
    expect($data['expenses']['total'])->toEqual(180);
    expect($data['expenses']['by_category'])->toEqual(['hosting' => 150, 'mpesa_charges' => 30]);
    expect($data['net_income'])->toEqual(220);
    expect($data['series'][0])->toMatchArray(['period' => '2026-09-20', 'total' => 400, 'expenses' => 180, 'net_income' => 220]);
});

it('dates expenses by expense date and groups them by period', function () {
    houseRevenue(100);
    recordExpense($this, ['expense_date' => '2026-09-14', 'amount' => 200, 'reference' => 'W1']);
    recordExpense($this, ['expense_date' => '2026-09-20', 'amount' => 50, 'reference' => 'W2']);

    $weeks = $this->getJson('/api/v1/finance/income-statement?from=2026-09-14&to=2026-09-20&group_by=week', $this->headers)->json('data');
    expect($weeks['series'])->toHaveCount(1);
    expect($weeks['series'][0])->toMatchArray(['expenses' => 250, 'net_income' => -150]);

    $days = $this->getJson('/api/v1/finance/income-statement?from=2026-09-14&to=2026-09-20', $this->headers)->json('data.series');
    expect($days[0])->toMatchArray(['period' => '2026-09-14', 'expenses' => 200, 'net_income' => -200]);
    expect($days[6])->toMatchArray(['period' => '2026-09-20', 'expenses' => 50, 'net_income' => 50]);
});

it('includes expenses and net income in the summary windows', function () {
    houseRevenue(300);
    recordExpense($this, ['expense_date' => '2026-09-20', 'amount' => 40, 'reference' => 'S1']);
    recordExpense($this, ['expense_date' => '2026-09-05', 'amount' => 60, 'reference' => 'S2']);

    $windows = $this->getJson('/api/v1/finance/summary', $this->headers)->assertOk()->json('data.windows');

    expect($windows['today'])->toMatchArray(['expenses' => 40, 'net_income' => 260]);
    expect($windows['month'])->toMatchArray(['expenses' => 100, 'net_income' => 200]);
    expect($windows['all_time'])->toMatchArray(['expenses' => 100, 'net_income' => 200]);
});

// export

it('exports expenses and honours the same filters', function () {
    recordExpense($this, ['category' => 'hosting', 'amount' => 100, 'reference' => 'X1']);
    $voided = recordExpense($this, ['category' => 'sms', 'amount' => 25, 'reference' => 'X2']);
    voidExpense($this, $voided['id'], 'duplicate')->assertOk();

    $csv = fn (string $query) => collect(explode("\n", trim(ltrim($this->get("/api/v1/finance/export/expenses{$query}", $this->headers)->streamedContent(), "\xEF\xBB\xBF"))))->map(fn (string $line) => str_getcsv($line));

    $active = $csv('');
    expect($active->first())->toBe(['id', 'expense_date', 'category', 'amount', 'description', 'reference', 'status', 'entered_by', 'created_at', 'voided_at', 'voided_by', 'void_reason']);
    expect($active)->toHaveCount(2);
    expect($active[1][2])->toBe('hosting');

    $all = $csv('?status=all');
    expect($all)->toHaveCount(3);
    expect(collect($all)->pluck(11)->all())->toContain('duplicate');
});
