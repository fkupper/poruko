<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects authenticated API users who have not configured 2FA when auth.enforce_2fa is true.
 *
 * Fortify registers enrollment routes under api/auth/user/* (outside this middleware group by
 * default). Enrollment must remain reachable when enforcement is on; the allowlist below covers
 * those paths if they are ever nested under this middleware.
 */
class EnforceTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $enforce = (bool) config('auth.enforce_2fa');

        if ($enforce && $request->user() && !$request->user()->two_factor_secret) {
            if (!$this->isExemptFromEnforcedTwoFactor($request)) {
                abort(403, 'Two-factor authentication must be enabled.');
            }
        }

        return $next($request);
    }

    private function isExemptFromEnforcedTwoFactor(Request $request): bool
    {
        /** @var list<string> $configured */
        $configured = config('auth.enforce_2fa_except', []);

        $patterns = $configured !== [] ? $configured : [
            'api/auth/logout',
            'auth/logout',
            'api/auth/user/two-factor-authentication',
            'api/auth/user/confirmed-two-factor-authentication',
            'api/auth/user/two-factor-qr-code',
            'api/auth/user/two-factor-secret-key',
            'api/auth/user/two-factor-recovery-codes',
            'api/auth/user/two-factor*',
            'auth/user/two-factor*',
        ];

        return $request->is(...$patterns);
    }
}
