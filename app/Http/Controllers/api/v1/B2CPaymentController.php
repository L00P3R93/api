<?php

namespace App\Http\Controllers\api\v1;

use App\Exceptions\MpesaApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreB2CPaymentRequest;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class B2CPaymentController extends Controller
{
    public function __construct(
        private MpesaService $mpesaService
    ) {}

    /**
     * Send money straight from the main B2C shortcode to a phone number. No wallet is debited and
     * nothing is written to the ledger: the payout shows only in the M-Pesa log and the float.
     */
    public function __invoke(StoreB2CPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Log::channel('mpesa')->info('MPESA B2C Direct Send Request', [
            'phone' => $validated['phone'],
            'amount' => $validated['amount'],
            'ip' => $request->ip(),
        ]);

        try {
            $response = $this->mpesaService->b2c([
                'Amount' => (int) $validated['amount'],
                'PartyB' => $validated['phone'],
                'Remarks' => $validated['remarks'] ?? 'Business Payment',
            ]);
        } catch (MpesaApiException $e) {
            Log::channel('mpesa')->error('MPESA B2C Direct Send Error: '.$e->getMessage());

            return response()->json(['status' => $e->getMessage()], 500);
        } catch (\InvalidArgumentException $e) {
            Log::channel('mpesa')->error('MPESA B2C Direct Send Config Error: '.$e->getMessage());

            return response()->json(['status' => 'B2C shortcode is not configured'], 503);
        }

        Log::channel('mpesa')->info('MPESA B2C Direct Send Response', $response);

        if ((string) ($response['ResponseCode'] ?? '') !== '0') {
            return response()->json([
                'status' => $response['ResponseDescription'] ?? 'Unknown Error',
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'phone' => $validated['phone'],
            'amount' => (int) $validated['amount'],
            'conversation_id' => $response['ConversationID'] ?? null,
            'originator_conversation_id' => $response['OriginatorConversationID'] ?? null,
        ], 201);
    }
}
