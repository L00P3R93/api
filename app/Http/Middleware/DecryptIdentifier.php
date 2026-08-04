<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;


class DecryptIdentifier
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route('encryptedIdentifier')) {
            try {
                $decryptedId = decryptOpenSSL($request->route('encryptedIdentifier'));
                if(empty($decryptedId)) {
                    Log::error("Invalid identifier [Unable to Decrypt]: $decryptedId");
                    return response()->json(['message' => 'Invalid identifier.'], 400);
                }
                $request->route()->setParameter('encryptedIdentifier', $decryptedId);
            } catch (\Exception $e) {
                Log::error('Invalid identifier: ' . $e->getMessage());
                return response()->json(['message' => 'Invalid identifier.'], 400);
            }
        }

        return $next($request);
    }
}
