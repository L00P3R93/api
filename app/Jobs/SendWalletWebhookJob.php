<?php

namespace App\Jobs;

use App\Exceptions\WalletWebhookFatalException;
use App\Exceptions\WalletWebhookRetryableException;
use App\Services\WalletWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWalletWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 1 initial attempt + 5 retries, matching the contract's backoff schedule.
     */
    public int $tries = 6;

    public int $timeout = 10;

    public function __construct(
        public readonly int $walletId,
        public readonly int $customerId,
        public readonly string $eventId,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 120, 600, 1800];
    }

    public function handle(WalletWebhookService $service): void
    {
        Cache::forget("wallet-webhook:debounce:{$this->walletId}");

        if (! $service->isEnabled()) {
            return;
        }

        // Frozen on first execution and reused verbatim on every retry, so
        // event_id stays paired with the same balance/balance_version even
        // if the wallet changes again before a retry runs.
        $snapshot = Cache::remember(
            "wallet-webhook:snapshot:{$this->eventId}",
            now()->addHours(2),
            fn () => $service->buildSnapshot($this->walletId, $this->customerId)
        );

        if ($snapshot === null) {
            Log::channel('wallet-webhook')->warning('wallet_missing', [
                'event_id' => $this->eventId,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        try {
            $service->send($this->eventId, $snapshot);
        } catch (WalletWebhookRetryableException $e) {
            Log::channel('wallet-webhook')->warning('delivery_attempt_failed', [
                'event_id' => $this->eventId,
                'attempt' => $this->attempts(),
                'status' => $e->statusCode,
                'outcome' => 'retrying',
            ]);

            throw $e;
        } catch (WalletWebhookFatalException $e) {
            Log::channel('wallet-webhook')->error('delivery_attempt_failed', [
                'event_id' => $this->eventId,
                'attempt' => $this->attempts(),
                'status' => $e->statusCode,
                'outcome' => 'stopped_non_retryable',
            ]);

            $this->fail($e);

            return;
        }

        Log::channel('wallet-webhook')->info('delivery_succeeded', [
            'event_id' => $this->eventId,
            'attempt' => $this->attempts(),
            'outcome' => 'success',
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::channel('wallet-webhook')->error('delivery_failed_final', [
            'event_id' => $this->eventId,
            'attempt' => $this->attempts(),
            'outcome' => 'exhausted_retries',
        ]);
    }
}
