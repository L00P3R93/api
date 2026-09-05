<?php

namespace App\Console\Commands;

use App\Services\BalanceService;
use Illuminate\Console\Command;

class FetchMpesaBalances extends Command
{
    protected $signature = 'mpesa:fetch-balances';

    protected $description = 'Fetch and store B2C and C2B account balances from MPESA';

    public function __construct(private BalanceService $balanceService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Fetching B2C balance...');

        $b2cResult = $this->balanceService->fetchAndStoreB2CBalance();

        if ($b2cResult['success']) {
            $this->info('B2C balance request accepted (ConversationID: '.($b2cResult['conversation_id'] ?? 'N/A').').');
        } else {
            $this->error('B2C balance failed: '.$b2cResult['message']);
        }

        $this->info('Fetching C2B balance...');

        $c2bResult = $this->balanceService->fetchAndStoreC2BBalance();

        if ($c2bResult['success']) {
            $this->info('C2B balance request accepted (ConversationID: '.($c2bResult['conversation_id'] ?? 'N/A').').');
        } else {
            $this->error('C2B balance failed: '.$c2bResult['message']);
        }

        return ($b2cResult['success'] && $c2bResult['success']) ? Command::SUCCESS : Command::FAILURE;
    }
}
