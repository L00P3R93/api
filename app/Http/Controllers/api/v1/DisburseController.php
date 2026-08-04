<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use App\Mpesa\Init as Mpesa;

class DisburseController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke($identifier) {
        $withdraw = Withdraw::find($identifier);
        if(!$withdraw) return response()->json([ "message" => "Transaction not found" ], 404);
        if($withdraw->status == 1 and $withdraw->disburse == 1 and $withdraw->receipt == null and $withdraw->transactions()->first()->payment_type == Withdraw::class) {
            // Since we're using Sandbox M-PESA environment, we need to use test phone number - '254708374149'.
            // We're now live.
            $userParams = [
                'Amount' => $withdraw->amount,
                $withdraw->transactions()->first()->wallet->customer->phone_no,
                'Remarks' => 'Business Payment',
            ];
            $response = Mpesa::b2c($userParams);
            $response = json_decode($response);
            // Update Withdraw status = 2 (Disbursed), receipt = Transaction ID if M-PESA disbursement is successful
            // If not successful, update status = 3 (Failed), receipt = error, error_message = error message
            /**
             * If successfully disbursed, returns the following:
             * {
             * "ConversationID": "AG_20191219_00005797af5d7d75f652",
             * "OriginatorConversationID": "16740-34861180-1",
             * "ResponseCode": "0",
             * "ResponseDescription": "Accept the service request successfully."
             * }
             *
             * If failed, returns the following:
             * {
             * "requestId": "11728-2929992-1",
             * "errorCode": "401.002.01",
             * "errorMessage": "Error Occurred - Invalid Access Token - BJGFGOXv5aZnw90KkA4TDtu4Xdyf"
             * }
             */
            if($response->ResponseCode == 0) {
                $withdraw->disburse = 2;
                $withdraw->receipt = $response->ConversationID;
            }else{
                $withdraw->disburse = 3;
                $withdraw->receipt = $response->errorCode;
                $withdraw->error_message = $response->errorMessage;
            }
            $withdraw->save();
            // Return a success response
            return response()->json([
                'message' => 'Disbursement processed successfully.',
                'withdraw' => $withdraw,
            ], 200);
        }

        // If the disbursement is not a valid disbursement or is already processed
        return response()->json([
            'message' => 'Invalid or already processed Disburse request.'
        ], 400);
    }
}
