<?php

namespace App\Modules\Mcp\Services;

use App\Enums\McpTokenAbility;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

final class McpTokenService
{
    public const DEFAULT_SIGNED_URL_HOURS = 24;

    public const MAX_SIGNED_URL_HOURS = 168;

    public function endpointUrl(): string
    {
        return url('/mcp/poruko');
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function listFor(User $user): Collection
    {
        return $user->tokens()
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PersonalAccessToken $token): bool => $this->isMcpToken($token))
            ->values();
    }

    public function create(User $user, string $name): NewAccessToken
    {
        return $user->createToken($name, [McpTokenAbility::Mcp->value]);
    }

    public function isMcpToken(PersonalAccessToken $token): bool
    {
        $abilities = $token->abilities;

        if (!is_array($abilities)) {
            return false;
        }

        return in_array(McpTokenAbility::Mcp->value, $abilities, true)
            && !in_array('*', $abilities, true);
    }

    public function belongsTo(User $user, PersonalAccessToken $token): bool
    {
        return $token->tokenable_type === $user->getMorphClass()
            && (int) $token->tokenable_id === (int) $user->id
            && $this->isMcpToken($token);
    }

    /**
     * @return array<string, mixed>
     */
    public function clientConfig(string $plainTextToken): array
    {
        $url = $this->endpointUrl();
        $authorization = 'Bearer ' . $plainTextToken;

        return [
            'url' => $url,
            'headers' => [
                'Authorization' => $authorization,
            ],
            'cursor' => [
                'mcpServers' => [
                    'poruko' => [
                        'url' => $url,
                        'headers' => [
                            'Authorization' => $authorization,
                        ],
                    ],
                ],
            ],
            'claude' => [
                'mcpServers' => [
                    'poruko' => [
                        'type' => 'http',
                        'url' => $url,
                        'headers' => [
                            'Authorization' => $authorization,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{url: string, expires_at: string, expires_in_hours: int}
     */
    public function signedUrl(PersonalAccessToken $token, int $expiresInHours = self::DEFAULT_SIGNED_URL_HOURS): array
    {
        $hours = max(1, min(self::MAX_SIGNED_URL_HOURS, $expiresInHours));
        $expiresAt = now()->addHours($hours);
        $url = URL::temporarySignedRoute(
            'mcp.poruko',
            $expiresAt,
            ['mcp_token' => $token->id],
        );

        return [
            'url' => $url,
            'expires_at' => $expiresAt->toISOString(),
            'expires_in_hours' => $hours,
        ];
    }
}
