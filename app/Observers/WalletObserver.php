<?php

namespace App\Observers;

use App\Models\Wallet;
use App\Services\WalletWebhookService;
use Illuminate\Support\Facades\Log;
use Throwable;

class WalletObserver
{
    public function __construct(private WalletWebhookService $webhookService) {}

    /**
     * Bump wallet_version atomically alongside every balance change, at the
     * SQL level (wallet_version = wallet_version + 1) so it stays correct
     * regardless of which caller/locking pattern wrote the balance.
     *
     * Uses the model's own connection rather than the DB facade so this
     * keeps working under tests that mock the DB facade for an unrelated
     * reason (e.g. to assert DB::transaction() was called).
     */
    public function updating(Wallet $wallet): void
    {
        if ($wallet->isDirty('balance')) {
            $wallet->wallet_version = $wallet->getConnection()->raw('wallet_version + 1');
        }
    }

    /**
     * `updated` (not `saved`) so a brand-new wallet's initial balance never
     * counts as a "change" — there is no prior balance to compare against.
     */
    public function updated(Wallet $wallet): void
    {
        if (! $wallet->wasChanged('balance')) {
            return;
        }

        $walletId = $wallet->id;
        $customerId = $wallet->customer_id;
        $webhookService = $this->webhookService;

        // Runs immediately if no transaction is open, or after COMMIT if one is —
        // either way, never on rollback. Wrapped defensively: a failure to
        // schedule the webhook must never affect the wallet write itself.
        $wallet->getConnection()->afterCommit(function () use ($webhookService, $walletId, $customerId) {
            try {
                $webhookService->scheduleForWallet($walletId, $customerId);
            } catch (Throwable $e) {
                Log::channel('wallet-webhook')->error('schedule_failed', [
                    'wallet_id' => $walletId,
                ]);
            }
        });
    }
}
