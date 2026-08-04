<?php

namespace App\Console\Commands;

use App\Services\SmsCodeService;
use Illuminate\Console\Command;

class ExpireSMSCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:expire-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marks SMS codes with status 1 or 2 as expired if older than 2 minutes.';

    /**
     * Create a new command instance.
     *
     * Inject the SMSCodeService
     *
     * @param SmsCodeService $smsCodeService Service for handling SMS code operations.
     */
    public function __construct(protected SmsCodeService $smsCodeService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredCount = $this->smsCodeService->expireOldCodes();
        $this->info("✅ {$expiredCount} SMS code(s) marked as expired.");
    }
}
