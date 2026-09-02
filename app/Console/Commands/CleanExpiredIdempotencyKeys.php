<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;

class CleanExpiredIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:clean';

    protected $description = 'Remove expired idempotency keys older than 24 hours';

    public function handle(): int
    {
        $deleted = IdempotencyKey::where('expires_at', '<', now())->delete();

        $this->info("Deleted {$deleted} expired idempotency key(s).");

        return self::SUCCESS;
    }
}
