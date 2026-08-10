<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $enforce = filter_var(env('ENFORCE_2FA', false), FILTER_VALIDATE_BOOLEAN);

        if ($enforce && $request->user() && !$request->user()->two_factor_secret) {
            if (!$request->is('api/auth/logout', 'api/auth/user/two-factor*')) {
                abort(403, 'Two-factor authentication must be enabled.');
            }
        }

        return $next($request);
    }
}
