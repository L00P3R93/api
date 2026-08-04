<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchasesResource;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return PurchasesResource::collection(Purchase::where('test', 0)->get());
    }
    
    public function referralPurchases(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $referralCodes = Str::contains($request->referral_code, ',')
            ? explode(',', $request->referral_code)
            : [$request->referral_code];
        return PurchasesResource::collection(Purchase::query()->whereIn('referral_code', $referralCodes)->get());
    }


    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier): \Illuminate\Http\JsonResponse|PurchasesResource
    {
        $purchases = Purchase::query()->find($encryptedIdentifier);
        if(!$purchases) return response()->json([ "message" => "Purchase not found" ], 404);
        return PurchasesResource::make($purchases);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier): \Illuminate\Http\JsonResponse
    {
        $purchases = Purchase::query()->find($encryptedIdentifier);
        if(!$purchases) return response()->json([ "message" => "Purchase not found" ], 404);
        $purchases->delete();
        return response()->json([ "message" => "Purchase deleted successfully" ], 200);
    }
}
