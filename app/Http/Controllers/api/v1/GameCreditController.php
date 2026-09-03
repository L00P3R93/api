<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGameCreditRequest;
use App\Models\Customer;
use App\Models\GameTransaction;
use App\Models\GameWallet;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GameCreditController extends Controller
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    public function __invoke(StoreGameCreditRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $customer = Customer::with('wallet')->find($validated['customer_id']);
        $gameWallet = GameWallet::find($validated['game_wallet_id']);

        if (! $customer->wallet) {
            return response()->json(['error' => 'Customer has no wallet.'], 400);
        }

        $wallet = $customer->wallet;

        if ($wallet->balance < $validated['amount']) {
            return response()->json(['error' => 'Insufficient wallet balance.'], 400);
        }

        $amount = (float) $validated['amount'];
        $houseShare = round($amount * 0.05, 2);

        try {
            DB::transaction(function () use ($validated, $wallet, $gameWallet, $amount, $houseShare) {
                $gameTransaction = GameTransaction::create([
                    'game_wallet_id' => $gameWallet->id,
                    'customer_id' => $validated['customer_id'],
                    'payment_type' => 'deposit',
                    'amount' => $amount,
                    'status' => 2,
                ]);

                [$walletEntry, $gameEntry] = $this->ledgerService->recordGameBetWithHouseCut(
                    $gameTransaction,
                    $wallet,
                    $gameWallet,
                    $amount,
                    $houseShare
                );

                $gameTransaction->update([
                    'wallet_balance_before' => $walletEntry->balance_before,
                    'wallet_balance_after' => $walletEntry->balance_after,
                    'game_balance_before' => $gameEntry->balance_before,
                    'game_balance_after' => $gameEntry->balance_after,
                ]);

                if ($houseShare > 0) {
                    $houseWallet = Wallet::find(config('wallets.house_wallet_id', 1));
                    $this->ledgerService->recordHouseCut(
                        $houseWallet,
                        $houseShare,
                        'game_credit'
                    );
                }
            });

            return response()->json(['status' => 'success'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
