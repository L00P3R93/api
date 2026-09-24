<?php

use App\Exceptions\WalletWebhookFatalException;
use App\Exceptions\WalletWebhookRetryableException;
use App\Jobs\SendWalletWebhookJob;
use App\Models\Customer;
use App\Models\Wallet;
use App\Services\WalletService;
use App\Services\WalletWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'wallet-webhook.enabled' => true,
        'wallet-webhook.url' => 'https://kadi-site.test/api/v1/wallet-webhooks',
        'wallet-webhook.secret' => str_repeat('a', 32),
        'wallet-webhook.debounce_seconds' => 2,
        'wallets.house_wallet_id' => 1,
    ]);

    // The house wallet takes id 1, so player wallets created by the tests never collide with it.
    $house = Customer::factory()->create(['id' => 1]);
    Wallet::forceCreate(['id' => 1, 'customer_id' => $house->id, 'balance' => 0]);
});

function webhookWallet(float $balance = 100): Wallet
{
    $customer = Customer::factory()->create();

    return Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => $balance]);
}

// --- dispatch / debounce ---

it('fires the webhook job once for a committed balance change', function () {
    Queue::fake();
    $wallet = webhookWallet();

    app(WalletService::class)->addBalance($wallet->id, 10);

    Queue::assertPushed(SendWalletWebhookJob::class, 1);
    Queue::assertPushed(function (SendWalletWebhookJob $job) use ($wallet) {
        return $job->walletId === $wallet->id && $job->customerId === $wallet->customer_id;
    });
});

it('does not fire the webhook job when the transaction rolls back', function () {
    Queue::fake();
    $wallet = webhookWallet();

    try {
        DB::transaction(function () use ($wallet) {
            $wallet->update(['balance' => $wallet->balance + 10]);
            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    Queue::assertNotPushed(SendWalletWebhookJob::class);
    expect((float) $wallet->fresh()->balance)->toBe(100.0);
});

it('coalesces a burst of balance changes on the same wallet into a single dispatch', function () {
    Queue::fake();
    $wallet = webhookWallet();
    $walletService = app(WalletService::class);

    $walletService->addBalance($wallet->id, 5);
    $walletService->addBalance($wallet->id, 5);
    $walletService->addBalance($wallet->id, 5);

    Queue::assertPushed(SendWalletWebhookJob::class, 1);
});

it('does not dispatch anything when the feature flag is off', function () {
    Queue::fake();
    config(['wallet-webhook.enabled' => false]);
    $wallet = webhookWallet();

    app(WalletService::class)->addBalance($wallet->id, 10);

    Queue::assertNotPushed(SendWalletWebhookJob::class);
});

it('does not dispatch anything when the url or secret is unset', function () {
    Queue::fake();
    config(['wallet-webhook.url' => null]);
    $wallet = webhookWallet();

    app(WalletService::class)->addBalance($wallet->id, 10);

    Queue::assertNotPushed(SendWalletWebhookJob::class);
});

// --- house wallet ---

it('never schedules a webhook for the house wallet', function () {
    Queue::fake();
    $player = webhookWallet();

    app(WalletService::class)->addBalance(1, 10);
    app(WalletService::class)->addBalance($player->id, 10);

    Queue::assertPushed(SendWalletWebhookJob::class, 1);
    Queue::assertPushed(fn (SendWalletWebhookJob $job) => $job->walletId === $player->id);
});

it('follows HOUSE_WALLET_ID when deciding which wallet is the house', function () {
    Queue::fake();
    $otherHouse = webhookWallet();
    config(['wallets.house_wallet_id' => $otherHouse->id]);

    app(WalletService::class)->addBalance($otherHouse->id, 10);
    app(WalletService::class)->addBalance(1, 10);

    Queue::assertPushed(SendWalletWebhookJob::class, 1);
    Queue::assertPushed(fn (SendWalletWebhookJob $job) => $job->walletId === 1);
});

it('refuses to smoke-test the house wallet', function () {
    Http::fake();

    $this->artisan('wallet-webhook:send', ['customer_id' => 1])
        ->expectsOutputToContain('The house wallet never sends wallet webhooks.')
        ->assertFailed();

    Http::assertNothingSent();
});

it('still smoke-tests a player wallet', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $player = webhookWallet();

    $this->artisan('wallet-webhook:send', ['customer_id' => $player->customer_id])->assertSuccessful();

    Http::assertSentCount(1);
});

// --- signing / HTTP wire format ---

it('signs requests with headers that verify against an independently computed HMAC', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $wallet = webhookWallet();
    $service = app(WalletWebhookService::class);
    $snapshot = $service->buildSnapshot($wallet->id, $wallet->customer_id);

    $service->send((string) Str::uuid(), $snapshot);

    Http::assertSent(function ($request) {
        $secret = config('wallet-webhook.secret');
        $timestamp = $request->header('X-Kadi-Timestamp')[0] ?? null;
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->body(), $secret);

        return $request->hasHeader('X-Kadi-Event-Id')
            && $request->hasHeader('X-Kadi-Timestamp')
            && $request->header('X-Kadi-Signature')[0] === $expected
            && $request->hasHeader('Content-Type')
            && $request->hasHeader('Accept');
    });
});

it('sends a body with exactly the contract fields and no PII', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $wallet = webhookWallet();
    $service = app(WalletWebhookService::class);
    $snapshot = $service->buildSnapshot($wallet->id, $wallet->customer_id);

    $service->send((string) Str::uuid(), $snapshot);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        expect(array_keys($body))->toEqualCanonicalizing([
            'event', 'event_id', 'customer_id', 'balance', 'balance_version', 'reason', 'occurred_at',
        ]);

        foreach (['phone_no', 'phone', 'name', 'email', 'description', 'transaction_description'] as $forbidden) {
            expect($body)->not->toHaveKey($forbidden);
        }

        return true;
    });
});

it('retries on 5xx and 429, does not retry on other 4xx', function (int $status, bool $retryable) {
    Http::fake(['*' => Http::response('', $status)]);
    $wallet = webhookWallet();
    $service = app(WalletWebhookService::class);
    $snapshot = $service->buildSnapshot($wallet->id, $wallet->customer_id);

    $thrown = null;
    try {
        $service->send((string) Str::uuid(), $snapshot);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf($retryable ? WalletWebhookRetryableException::class : WalletWebhookFatalException::class);
})->with([
    'retries on 500' => [500, true],
    'retries on 503' => [503, true],
    'retries on 429' => [429, true],
    'stops on 400' => [400, false],
    'stops on 401' => [401, false],
    'stops on 403' => [403, false],
    'stops on 422' => [422, false],
]);

it('retries on a connection/timeout error', function () {
    Http::fake(function () {
        throw new ConnectionException('timed out');
    });
    $wallet = webhookWallet();
    $service = app(WalletWebhookService::class);
    $snapshot = $service->buildSnapshot($wallet->id, $wallet->customer_id);

    expect(fn () => $service->send((string) Str::uuid(), $snapshot))
        ->toThrow(WalletWebhookRetryableException::class);
});

it('keeps event_id and the payload stable across retries while refreshing the timestamp', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $wallet = webhookWallet();
    $eventId = (string) Str::uuid();

    Carbon\Carbon::setTestNow('2026-01-01 00:00:00');
    (new SendWalletWebhookJob($wallet->id, $wallet->customer_id, $eventId))
        ->handle(app(WalletWebhookService::class));

    // Balance changes between "attempts" via a raw write, bypassing the
    // observer, so it doesn't schedule its own separate webhook here —
    // isolating this test to the job's own freeze-across-retries behaviour.
    DB::table('wallets')->where('id', $wallet->id)->update(['balance' => 999]);

    Carbon\Carbon::setTestNow('2026-01-01 00:00:15');
    (new SendWalletWebhookJob($wallet->id, $wallet->customer_id, $eventId))
        ->handle(app(WalletWebhookService::class));

    Carbon\Carbon::setTestNow();

    $requests = collect(Http::recorded())->map(fn ($pair) => $pair[0]);
    expect($requests)->toHaveCount(2);

    [$first, $second] = [$requests[0], $requests[1]];

    expect($first->header('X-Kadi-Event-Id')[0])->toBe($second->header('X-Kadi-Event-Id')[0]);
    expect($first->header('X-Kadi-Timestamp')[0])->not->toBe($second->header('X-Kadi-Timestamp')[0]);

    $firstBody = json_decode($first->body(), true);
    $secondBody = json_decode($second->body(), true);
    expect($firstBody['balance'])->toBe($secondBody['balance']);
    expect($firstBody['balance_version'])->toBe($secondBody['balance_version']);
});

it('is a safe no-op with no HTTP call when the feature is disabled', function () {
    Http::fake(['*' => Http::response('', 200)]);
    config(['wallet-webhook.enabled' => false]);
    $wallet = webhookWallet();

    (new SendWalletWebhookJob($wallet->id, $wallet->customer_id, (string) Str::uuid()))
        ->handle(app(WalletWebhookService::class));

    Http::assertNothingSent();
});

// --- the wallet write must never be affected ---

it('never lets a failing webhook (retryable) affect the wallet write', function () {
    Http::fake(['*' => Http::response('', 500)]);
    $wallet = webhookWallet();

    $result = app(WalletService::class)->addBalance($wallet->id, 25);

    expect($result['success'])->toBeTrue();
    expect((float) $wallet->fresh()->balance)->toBe(125.0);
});

it('never lets a failing webhook (non-retryable) affect the wallet write', function () {
    Http::fake(['*' => Http::response('', 422)]);
    $wallet = webhookWallet();

    $result = app(WalletService::class)->addBalance($wallet->id, 15);

    expect($result['success'])->toBeTrue();
    expect((float) $wallet->fresh()->balance)->toBe(115.0);
});
