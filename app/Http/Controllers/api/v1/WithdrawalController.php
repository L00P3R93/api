<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalService $withdrawalService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $identifier): JsonResponse
    {
        try {
            $validatedData = $request->validate(['amount' => 'required|numeric|min:1']);

            $result = $this->withdrawalService->initiateWithdrawal($identifier, $validatedData['amount']);

            $statusCode = $result['status_code'] ?? 500;

            return response()->json([
                'status' => $result['success'] ? 'success' : $result['message'],
                'ledger_entry_id' => $result['ledger_entry_id'] ?? null,
            ], $statusCode);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::channel('mpesa')->error('Withdraw Request Error: '.$e->getMessage(), [
                'identifier' => $identifier,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => $e->getMessage(),
            ], 500);
        }
    }
}
