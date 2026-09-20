<?php

namespace App\Http\Middleware;

use App\Enums\McpTokenAbility;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateMcp
{
    /**
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->userFromBearer($request) ?? $this->userFromSignedUrl($request);

        if (!$user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication is required.');
        }

        $accessToken = $user->currentAccessToken();

        if (!$accessToken instanceof HasAbilities || !$accessToken->can(McpTokenAbility::Mcp->value)) {
            abort(Response::HTTP_FORBIDDEN, 'This credential cannot access MCP.');
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private function userFromBearer(Request $request): ?User
    {
        if (!$request->bearerToken()) {
            return null;
        }

        $user = Auth::guard('sanctum')->user();

        return $user instanceof User ? $user : null;
    }

    private function userFromSignedUrl(Request $request): ?User
    {
        $tokenId = $request->query('mcp_token');

        if (!is_numeric($tokenId) || !$request->hasValidSignature()) {
            return null;
        }

        $accessToken = PersonalAccessToken::query()->find((int) $tokenId);

        if (!$accessToken instanceof PersonalAccessToken) {
            return null;
        }

        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            return null;
        }

        $user = $accessToken->tokenable;

        if (!$user instanceof User) {
            return null;
        }

        $user->withAccessToken($accessToken);
        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $user;
    }
}
