<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateLogViewerBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('log-viewer.basic_auth.username');
        $password = config('log-viewer.basic_auth.password');

        if (! $username || ! $password) {
            return response('Log Viewer credentials are not configured.', 500);
        }

        $providedUsername = $request->getUser() ?? '';
        $providedPassword = $request->getPassword() ?? '';

        if (! hash_equals($username, $providedUsername) || ! hash_equals($password, $providedPassword)) {
            return response('Unauthorized.', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}
