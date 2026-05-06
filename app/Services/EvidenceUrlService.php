<?php

namespace App\Services;

use App\Models\TrustedEvidenceSource;
use InvalidArgumentException;

class EvidenceUrlService
{
    public function inspect(?string $url): array
    {
        if (! $url) {
            return [
                'host' => null,
                'type' => null,
                'safety' => 'none',
                'trusted' => false,
                'preview_url' => null,
            ];
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Evidence URL must use http or https.');
        }

        if (! $this->isFetchableHost($host)) {
            throw new InvalidArgumentException('Evidence URL host is not allowed.');
        }

        $type = $this->evidenceType($host, $path);
        $trusted = in_array($host, config('truthshield.trusted_evidence_hosts', []), true)
            || TrustedEvidenceSource::query()->where('host', $host)->where('is_active', true)->exists();

        return [
            'host' => $host,
            'type' => $type,
            'safety' => $trusted ? 'trusted' : 'unverified',
            'trusted' => $trusted,
            'preview_url' => $this->previewUrl($url, $host, $path, $type),
        ];
    }

    public function evidenceType(?string $host, ?string $path): string
    {
        if (
            in_array($host, ['imgur.com', 'www.imgur.com', 'i.imgur.com'], true)
            || preg_match('/\.(png|jpe?g|webp|gif)$/', (string) $path)
        ) {
            return 'image';
        }

        return 'link';
    }

    private function previewUrl(string $url, string $host, string $path, string $type): ?string
    {
        if ($type !== 'image') {
            return null;
        }

        if (in_array($host, ['imgur.com', 'www.imgur.com'], true)) {
            $id = collect(explode('/', trim($path, '/')))->filter()->last();
            if ($id && ! str_contains($id, '.')) {
                return "https://i.imgur.com/{$id}.jpg";
            }
        }

        return $url;
    }

    public function assertFetchableUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($scheme, ['http', 'https'], true) || ! $this->isFetchableHost($host)) {
            throw new InvalidArgumentException('Evidence URL host is not allowed.');
        }
    }

    public function isAllowedContentType(?string $contentType): bool
    {
        if (! $contentType) {
            return true;
        }

        $normalized = strtolower(trim(strtok($contentType, ';') ?: $contentType));

        return in_array($normalized, config('truthshield.evidence_snapshot_allowed_content_types', []), true);
    }

    private function isFetchableHost(?string $host): bool
    {
        if (! $host) {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        foreach (gethostbynamel($host) ?: [] as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
