<?php

namespace App\Modules\Ledger\Services;

use InvalidArgumentException;

final class OpenAiCompatibleEndpoint
{
    public static function assertValidBaseUrl(string $baseUrl): void
    {
        self::parsed($baseUrl);
    }

    public static function normalizeForStorage(string $baseUrl): string
    {
        self::assertValidBaseUrl($baseUrl);

        return rtrim(trim($baseUrl), '/');
    }

    public static function identity(string $baseUrl): string
    {
        $parts = self::parsed($baseUrl);
        $path = rtrim($parts['path'], '/');
        $query = $parts['query'] === null ? '' : '?' . $parts['query'];

        return self::origin($parts) . $path . $query;
    }

    public static function completionsUrl(string $baseUrl): string
    {
        $parts = self::parsed($baseUrl);
        $path = rtrim($parts['path'], '/');
        $normalizedPath = mb_strtolower($path);

        if ($normalizedPath === '') {
            $path = '/v1/chat/completions';
        } elseif (!str_ends_with($normalizedPath, '/chat/completions')) {
            $path .= preg_match('#(?:^|/)v1(?:/|$)#', $normalizedPath) === 1
                ? '/chat/completions'
                : '/v1/chat/completions';
        }

        if ($path === '' || !str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        $query = $parts['query'] === null ? '' : '?' . $parts['query'];

        return self::origin($parts) . $path . $query;
    }

    /**
     * @return array{scheme: string, host: string, port: int|null, path: string, query: string|null}
     */
    private static function parsed(string $baseUrl): array
    {
        $parts = parse_url(trim($baseUrl));

        if ($parts === false) {
            throw new InvalidArgumentException('Enter a valid http(s) base URL for the OpenAI-compatible endpoint.');
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (!is_string($scheme) || !in_array(mb_strtolower($scheme), ['http', 'https'], true)) {
            throw new InvalidArgumentException('The base URL must use http or https.');
        }

        if (!is_string($host) || $host === '') {
            throw new InvalidArgumentException('Enter a valid http(s) base URL for the OpenAI-compatible endpoint.');
        }

        if (array_key_exists('user', $parts) || array_key_exists('pass', $parts)) {
            throw new InvalidArgumentException('The base URL must not include credentials. Use the API key field instead.');
        }

        $port = $parts['port'] ?? null;
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? null;

        return [
            'scheme' => mb_strtolower($scheme),
            'host' => mb_strtolower($host),
            'port' => is_int($port) ? $port : null,
            'path' => is_string($path) ? $path : '',
            'query' => is_string($query) ? $query : null,
        ];
    }

    /**
     * @param array{scheme: string, host: string, port: int|null, path: string, query: string|null} $parts
     */
    private static function origin(array $parts): string
    {
        $host = $parts['host'];

        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }

        $port = $parts['port'] === null ? '' : ':' . $parts['port'];

        return $parts['scheme'] . '://' . $host . $port;
    }
}
