<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\CompetitionWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompetitionWalletWithdrawController extends Controller
{
    public function __construct(
        private CompetitionWalletService $walletService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier)
    {
        $request->validate([
            'customer_id' => 'required|string',
        ]);

        try {
            $this->walletService->processWithdrawal($encryptedIdentifier, $request->customer_id);

            return response()->json(['status' => 'success'], 200);
        } catch (\InvalidArgumentException $e) {
            $status = match (true) {
                str_contains($e->getMessage(), 'not found') => 404,
                default => 400,
            };

            return response()->json(['status' => $e->getMessage()], $status);
        } catch (\Exception $e) {
            Log::error('Competition Wallet Withdrawal Error', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
                'competition_wallet_id' => $encryptedIdentifier,
            ]);

            return response()->json([
                'error' => 'An error occurred during the withdrawal process.',
            ], 500);
        }
    }
}
