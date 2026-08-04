<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class B2CResultController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request) {
        // Log incoming request
        Log::channel('mpesa')->info('MPESA B2C Response: ', $request->all());
        try {
            $transaction = Transaction::where('payment_ref', $request->ConversationID)->first();
            if (!$transaction) { Log::channel('mpesa')->info('MPESA B2C Transaction Not Found: ', $request->all());}
            else{
                $status = $request->ResultCode == 0 ? 2 : 3;
                $receipt = $request->ResultCode == 0 ? $request->TransactionID : $request->ResultDesc;
                $transaction->update([
                    'payment_ref' => $receipt,
                    'status' => $status,
                ]);
            }
        } catch (\Exception $e) {
            // Log and return error
            Log::error('MPESA B2C Error: ', ['error' => $e->getMessage()]);

        }
    }
}
