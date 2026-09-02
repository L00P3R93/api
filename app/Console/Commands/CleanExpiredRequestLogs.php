<?php

namespace App\Console\Commands;

use App\Models\RequestLog;
use Illuminate\Console\Command;

class CleanExpiredRequestLogs extends Command
{
    protected $signature = 'logs:clean {--days=90}';

    protected $description = 'Remove request logs older than the specified number of days (default: 90)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = RequestLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} request log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
