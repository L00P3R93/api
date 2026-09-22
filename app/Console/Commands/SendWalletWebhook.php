<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Services\WalletWebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class SendWalletWebhook extends Command
{
    protected $signature = 'wallet-webhook:send {customer_id}';

    protected $description = 'Send the current wallet balance webhook for one customer, for smoke testing against a real site';

    public function __construct(private WalletWebhookService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $customerId = (int) $this->argument('customer_id');
        $wallet = Wallet::where('customer_id', $customerId)->first();

        if (! $wallet) {
            $this->error("No wallet found for customer {$customerId}.");

            return Command::FAILURE;
        }

        // Gates on isConfigured(), not isEnabled(), so this works for smoke
        // testing while the feature is still deployed dark.
        if (! $this->service->isConfigured()) {
            $this->error('KADI_SITE_WEBHOOK_URL / KADI_SITE_WEBHOOK_SECRET are not set.');

            return Command::FAILURE;
        }

        $eventId = (string) Str::uuid();
        $snapshot = $this->service->buildSnapshot($wallet->id, $customerId);

        try {
            $this->service->send($eventId, $snapshot);
        } catch (Throwable $e) {
            $this->error("Delivery failed: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $this->info("Sent wallet.updated for customer {$customerId} (event_id={$eventId}, balance={$snapshot['balance']}, balance_version={$snapshot['balance_version']}).");

        return Command::SUCCESS;
    }
}
