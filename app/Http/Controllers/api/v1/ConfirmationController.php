<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\C2BConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfirmationController extends Controller
{
    public function __construct(
        private C2BConfirmationService $confirmationService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            Log::channel('mpesa')->info('MPESA Confirmation Received', $request->all());

            $depositData = [
                'trans_id' => $request->input('TransID'),
                'trans_type' => $request->input('TransactionType'),
                'trans_time' => date('Y-m-d H:i:s', strtotime($request->input('TransTime'))),
                'trans_amount' => $request->input('TransAmount'),
                'short_code' => $request->input('BusinessShortCode'),
                'bill_ref_no' => $request->input('BillRefNumber'),
                'msisdn' => $request->input('MSISDN'),
                'name' => trim(
                    $request->input('FirstName').' '.
                    $request->input('MiddleName').' '.
                    $request->input('LastName')
                ),
            ];

            $result = $this->confirmationService->processCallback($depositData);

            $statusCode = $result['status'] ?? 500;

            return response()->json([
                'ResultCode' => $result['ResultCode'],
                'ResultDesc' => $result['ResultDesc'],
            ], $statusCode);
        } catch (\Exception $e) {
            Log::channel('mpesa')->error('MPESA Confirmation Received [Error]: ', ['error' => $e->getMessage(), 'request' => $request->all()]);

            return response()->json([
                'ResultCode' => 'C2B00016',
                'ResultDesc' => $e->getMessage(),
            ], 500);
        }
    }
}
