<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $this->logRequest($request, $response, $startTime);

        return $response;
    }

    private function logRequest(Request $request, Response $response, float $startTime): void
    {
        try {
            $apiKey = $request->header('X-API-KEY');
            $apiKeyModel = $apiKey ? ApiKey::where('key', $apiKey)->first() : null;

            RequestLog::create([
                'api_key_id' => $apiKeyModel?->id,
                'ip_address' => $request->ip(),
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'request_hash' => hash('sha256', json_encode($request->except(['X-API-KEY']))),
                'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail — logging should never break the request
        }
    }
}
