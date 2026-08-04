<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EncryptIdentifierController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request) {
        try{
            $plainValue = $request->identifier;
            $encryptedIdentifier = encryptOpenSSL($plainValue);
            return response()->json(['decrypted_id' => $plainValue, 'encrypted_id' => $encryptedIdentifier]);
        }catch (\Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
