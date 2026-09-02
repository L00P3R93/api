<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\CoinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CoinBuyController extends Controller
{
    public function __construct(
        private CoinService $coinService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
        ]);

        try {
            $result = $this->coinService->buyCoins($encryptedIdentifier, $validated['amount']);

            $statusCode = $result['status_code'] ?? 500;

            return response()->json([
                'message' => $result['message'],
                'wallet_balance' => $result['wallet_balance'] ?? null,
                'coins' => $result['coins'] ?? null,
            ], $statusCode);
        } catch (\Exception $e) {
            Log::error('Coin Purchase Error: '.$e->getMessage());

            return response()->json(['message' => 'An error occurred during the transaction'], 500);
        }
    }
}
