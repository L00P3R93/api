<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\CompetitionPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompetitionWalletTransferPayoutController extends Controller
{
    public function __construct(
        private CompetitionPayoutService $payoutService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'sender_competition_wallet_id' => 'required|integer',
                'receiver_competition_wallet_id' => 'required|integer',
            ]);

            $result = $this->payoutService->processPayout(
                $validated['sender_competition_wallet_id'],
                $validated['receiver_competition_wallet_id']
            );

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            $status = match (true) {
                str_contains($e->getMessage(), 'Invalid') => 404,
                default => 400,
            };

            return response()->json(['error' => $e->getMessage()], $status);
        } catch (\Exception $e) {
            Log::error('Competition Wallet Transfer Payout Error', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'error' => 'An error occurred during the competition wallet payout process.',
            ], 500);
        }
    }
}
