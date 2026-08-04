<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use Illuminate\Http\Request;

class ApproveDisburseController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke($identifier){
        $withdraw = Withdraw::find($identifier);
        if(!$withdraw) return response()->json([ "message" => "Transaction not found" ], 404);
        if($withdraw->status == 1 and $withdraw->disburse == 0 and $withdraw->receipt == null and $withdraw->transactions()->first()->payment_type == Withdraw::class) {
            // Update withdrawal details
            $withdraw->disburse = 1;
            $withdraw->save();

            // Return a success response
            return response()->json([
                'message' => 'Disbursement request approved successfully.',
                'withdraw' => $withdraw,
            ], 200);
        }

        // If the disbursement is not a valid disbursement or is already processed
        return response()->json([
            'message' => 'Invalid or already processed Disburse request.'
        ], 400);
    }
}
