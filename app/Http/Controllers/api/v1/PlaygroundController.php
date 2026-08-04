<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


class PlaygroundController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $encryptedIdentifier = encryptOpenSSL('11');
        $decryptedIdentifier = decryptOpenSSL($encryptedIdentifier);
        return response()->json(['encrypted_id' => $encryptedIdentifier, 'decrypted_id' => $decryptedIdentifier]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $encryptedIdentifier){
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        return response()->json(array_merge(['decrypted_id' => $encryptedIdentifier], $request->all()));
    }

    /**
     * Display the specified resource.
     */
    public function show($identifier){
        $encryptedIdentifier = encryptOpenSSL($identifier);
        return response()->json(['decrypted_id' => $identifier, 'encrypted_id' => $encryptedIdentifier]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $encryptedIdentifier) {
        $identifier = $request->identifier;
        $encryptedIdentifier2 = encryptOpenSSL($identifier);
        return response()->json(array_merge(['middleware_decrypted_id' => $encryptedIdentifier, 'encrypted_id' => $encryptedIdentifier2], $request->all()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier)
    {
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        return response()->json(['delete_decrypted_id' => $encryptedIdentifier]);
    }
}
