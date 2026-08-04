<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSMSCodeRequest;
use App\Http\Resources\SMSCodeResource;
use App\Models\SMSCode;
use Illuminate\Support\Facades\Log;

class SMSCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(){
        return SMSCodeResource::collection(SMSCode::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSMSCodeRequest $request) {
        try {
            $smsCode = SMSCode::create($request->validated());
            return response()->json(["status" => "Success", "sms_code_id" => $smsCode->id ], 201);
        } catch (\Exception $e) {
            Log::error('Create SMS Code Error: ', ['error' => $e->getMessage()]);
            return response()->json([ "status" => "Error creating SMS Code" ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier) {
        $smsCode = SMSCode::find($encryptedIdentifier);
        if(!$smsCode) return response()->json([ "status" => "SMS Code not found" ], 404);
        return SMSCodeResource::make($smsCode);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier) {
        $smsCode = SMSCode::find($encryptedIdentifier);
        if(!$smsCode) return response()->json([ "status" => "SMS Code not found" ], 404);
        $smsCode->delete();
        return response()->json([ "status" => "Success" ]);
    }
}
