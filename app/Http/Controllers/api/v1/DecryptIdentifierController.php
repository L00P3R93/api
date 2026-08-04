<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DecryptIdentifierController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request) {
        try{
            $encryptedIdentifier = $request->identifier;
            $decryptedIdentifier = decryptOpenSSL($encryptedIdentifier);
            return response()->json(['decrypted_id' => $decryptedIdentifier, 'encrypted_id' => $encryptedIdentifier]);
        }catch (\Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
