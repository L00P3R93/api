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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route('encryptedIdentifier')) {
            $encryptedIdentifier = $request->route('encryptedIdentifier');
            Log::info('Encrypted Request: ', $this->requestContext($request, $encryptedIdentifier));

            try {
                $decryptedId = decryptOpenSSL($encryptedIdentifier);
                if (empty($decryptedId)) {
                    Log::error('Invalid identifier [Unable to Decrypt]', $this->requestContext($request, $encryptedIdentifier));

                    return response()->json(['message' => 'Invalid identifier.'], 400);
                }
                $request->route()->setParameter('encryptedIdentifier', $decryptedId);
            } catch (\Exception $e) {
                Log::error('Invalid identifier: '.$e->getMessage(), $this->requestContext($request, $encryptedIdentifier));

                return response()->json(['message' => 'Invalid identifier.'], 400);
            }
        }

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestContext(Request $request, string $encryptedIdentifier): array
    {
        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route_name' => $request->route()?->getName(),
            'encrypted_identifier' => $encryptedIdentifier,
            'query' => $request->query(),
            'request' => $request->all(),
            'headers' => collect($request->headers->all())
                ->except(['authorization', 'x-api-key', 'cookie'])
                ->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
