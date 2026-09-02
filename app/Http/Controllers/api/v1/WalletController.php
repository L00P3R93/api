<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return WalletResource::collection($this->walletService->listWallets());
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier): WalletResource|JsonResponse
    {
        $wallet = $this->walletService->getWallet($encryptedIdentifier);
        if (! $wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        return WalletResource::make($wallet);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWalletRequest $request, $encryptedIdentifier): JsonResponse
    {
        $wallet = $this->walletService->updateWallet($encryptedIdentifier, $request->validated());
        if (! $wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        return response()->json(['status' => 'success'], 201);
    }

    public function reduce_balance(Request $request, $encryptedIdentifier): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $result = $this->walletService->reduceBalance($encryptedIdentifier, $request->amount);
        if (! $result['success']) {
            $statusCode = $result['message'] === 'Wallet not found' ? 404 : 400;

            return response()->json(['message' => $result['message']], $statusCode);
        }

        return response()->json(['status' => 'success', 'balance_before' => $result['balance_before'], 'balance_after' => $result['balance_after']], 201);
    }

    public function add_balance(Request $request, $encryptedIdentifier): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $result = $this->walletService->addBalance($encryptedIdentifier, $request->amount);
        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 404);
        }

        return response()->json(['status' => 'success', 'balance_before' => $result['balance_before'], 'balance_after' => $result['balance_after']], 201);
    }

    public function update_balance(Request $request, $encryptedIdentifier): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $result = $this->walletService->setBalance($encryptedIdentifier, $request->amount);
        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 404);
        }

        return response()->json(['status' => 'success', 'balance_before' => $result['balance_before'], 'balance_after' => $result['balance_after']], 201);
    }
}
