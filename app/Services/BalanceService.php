<?php

namespace App\Services;

use App\Models\MpesaBalance;
use Illuminate\Support\Facades\Log;

class BalanceService
{
    public function __construct(private MpesaService $mpesaService) {}

    public function fetchAndStoreB2CBalance(): array
    {
        $response = $this->mpesaService->b2cAccountBalance();
        $data = json_decode($response, true);

        if (! $data || isset($data['error'])) {
            Log::channel('mpesa')->error('B2C Balance Request Failed', ['response' => $data]);

            return ['success' => false, 'message' => 'Failed to fetch B2C balance'];
        }

        return $this->parseAndStore('b2c', $data);
    }

    public function fetchAndStoreC2BBalance(): array
    {
        $response = $this->mpesaService->c2bAccountBalance();
        $data = json_decode($response, true);

        if (! $data || isset($data['error'])) {
            Log::channel('mpesa')->error('C2B Balance Request Failed', ['response' => $data]);

            return ['success' => false, 'message' => 'Failed to fetch C2B balance'];
        }

        return $this->parseAndStore('c2b', $data);
    }

    public function processBalanceResult(string $type, array $data): array
    {
        Log::channel('mpesa')->info("MPESA {$type} Balance Result Received", $data);

        return $this->parseAndStore($type, $data);
    }

    public function processBalanceTimeout(string $type, array $data): array
    {
        Log::channel('mpesa')->info("MPESA {$type} Balance Timeout Received", $data);

        return ['success' => false, 'message' => 'Balance request timed out'];
    }

    private function parseAndStore(string $type, array $data): array
    {
        $accountBalanceString = collect($data['Result']['ResultParameters']['ResultParameter'] ?? [])
            ->firstWhere('Key', 'AccountBalance')['Value'] ?? null;

        if (! $accountBalanceString) {
            Log::channel('mpesa')->error('No balance data found', $data);
            return ['success' => false, 'message' => 'No balance data found'];
        }

        $accounts = explode('&', $accountBalanceString);
        $stored = [];

        foreach ($accounts as $account) {
            $parts = explode('|', $account);
            $accountName = $parts[0] ?? null;
            $currency = $parts[1] ?? null;
            $balance = (float) ($parts[2] ?? 0);

            if ($accountName) {
                MpesaBalance::create([
                    'type' => $type,
                    'account_name' => $accountName,
                    'currency' => $currency,
                    'amount' => $balance,
                    'raw_response' => $data,
                ]);

                $stored[] = [
                    'account_name' => $accountName,
                    'currency' => $currency,
                    'amount' => $balance,
                ];
            }
        }

        return ['success' => true, 'balances' => $stored];
    }
}
