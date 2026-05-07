<?php

namespace App\Services;

use InvalidArgumentException;

class UrlFingerprintService
{
    /**
     * @return array{original_url: string, normalized_url: string, hash: string}
     */
    public function fingerprint(string $url): array
    {
        $originalUrl = trim($url);
        $parts = parse_url($originalUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('A valid absolute URL is required.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : preg_replace('#/+#', '/', $path);
        $path = preg_replace('#/amp/?$#i', '', $path) ?: '/';
        $path = preg_replace('#^/amp/#i', '/', $path);
        $path = $path !== '/' ? rtrim($path, '/') : $path;

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $portSuffix = match (true) {
            $port === null,
            $scheme === 'http' && $port === 80,
            $scheme === 'https' && $port === 443 => '',
            default => ':' . $port,
        };

        $query = $this->normalizeQuery($parts['query'] ?? '');
        $normalizedUrl = $scheme . '://' . $host . $portSuffix . $path . $query;
        $normalizedUrl = $this->normalizeVideoUrl($scheme, $host, $path, $parts['query'] ?? '') ?? $normalizedUrl;

        return [
            'original_url' => $originalUrl,
            'normalized_url' => $normalizedUrl,
            'hash' => hash('sha256', $normalizedUrl),
        ];
    }

    private function normalizeVideoUrl(string $scheme, string $host, string $path, string $query): ?string
    {
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            parse_str($query, $values);

            $videoId = match (true) {
                $path === '/watch' => $values['v'] ?? null,
                preg_match('#^/(shorts|live|embed)/([^/?]+)#', $path, $matches) === 1 => $matches[2],
                default => null,
            };

            if ($videoId) {
                return "{$scheme}://www.youtube.com/watch?v=" . rawurlencode((string) $videoId);
            }
        }

        if ($host === 'youtu.be') {
            $videoId = collect(explode('/', trim($path, '/')))->filter()->first();

            if ($videoId) {
                return "{$scheme}://www.youtube.com/watch?v=" . rawurlencode((string) $videoId);
            }
        }

        return null;
    }

    private function normalizeQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        parse_str($query, $values);
        $values = array_filter(
            $values,
            fn ($value, string $key): bool => ! $this->isTrackingQueryKey($key),
            ARRAY_FILTER_USE_BOTH,
        );
        ksort($values);

        if ($values === []) {
            return '';
        }

        return '?' . http_build_query($values, '', '&', PHP_QUERY_RFC3986);
    }

    private function isTrackingQueryKey(string $key): bool
    {
        $key = strtolower($key);

        return str_starts_with($key, 'utm_')
            || in_array($key, ['fbclid', 'gclid', 'dclid', 'mc_cid', 'mc_eid', 'igshid', 'ref', 'ref_src'], true);
    }
}
