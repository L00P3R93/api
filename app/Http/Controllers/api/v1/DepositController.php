<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepositRequest;
use App\Http\Resources\DepositResource;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return DepositResource::collection(Deposit::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepositRequest $request): JsonResponse {
        // Mapping the incoming validated data to the Deposit table columns
        $depositData = [
            'trans_id' => $request->input('TransID'),
            'trans_type' => $request->input('TransactionType'),
            'trans_time' => date('Y-m-d H:i:s', strtotime($request->input('TransTime'))),
            'trans_amount' => $request->input('TransAmount'),
            'short_code' => $request->input('BusinessShortCode'),
            'bill_ref_no' => $request->input('BillRefNumber'),
            'msisdn' => $request->input('MSISDN'),
            'name' => $request->input('FirstName') . ' ' . $request->input('MiddleName') . ' ' . $request->input('LastName'),
        ];

        // Record the payment
        $deposit = Deposit::create($depositData);

        // Extract customer info from BillRefNumber
        $accountNo = $deposit->bill_ref_no;
        $amount = $deposit->trans_amount;

        // Check if the customer exists
        $customer = Customer::where('id_no', $accountNo)->orWhere('account_no', $accountNo)->first();

        if(!$customer) {
            // Set deposit status to Suspense
            $deposit->update(['status' => 0]);
            return response()->json([
                'message' => 'Customer not found. Deposit moved to suspense.',
                'deposit' => $deposit
            ], 404);
        }

        // Retrieve or create a wallet for the customer
        $wallet = Wallet::firstOrCreate(
            ['customer_id' => $customer->id],
            ['balance' => 0]
        );

        // Update wallet balance
        $wallet->balance += $amount;
        $wallet->save();

        // Create a Transaction
        $transaction = $wallet->transactions()->create([
            'payment_id' => $deposit->id,
            'payment_ref' => $deposit->trans_id,
            'payment_type' => Deposit::class,
            'amount' => $amount,
            'status' => 2,
        ]);

        // Update deposit status to Completed
        $deposit->update(['status' => '2']);

        return response()->json([
            'message' => 'Deposit successful',
            'wallet' => $wallet,
            'transaction' => $transaction
        ],  201);
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier) {
        $deposit = Deposit::where('id', $encryptedIdentifier)->first();
        if(!$deposit) return response()->json([ "message" => "Payment not found" ], 404);
        return DepositResource::make($deposit);
    }
}
