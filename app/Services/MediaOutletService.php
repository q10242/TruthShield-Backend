<?php

namespace App\Services;

use App\Models\MediaOutlet;
use App\Models\NewsDomain;
use App\Models\NewsUrl;

class MediaOutletService
{
    public function attachOutlet(NewsUrl $newsUrl): void
    {
        if ($newsUrl->media_outlet_id) {
            return;
        }

        $outlet = $this->findOutletForUrl($newsUrl->normalized_url);

        if ($outlet) {
            $newsUrl->forceFill(['media_outlet_id' => $outlet->id])->save();
        }
    }

    public function findOutletForUrl(string $url): ?MediaOutlet
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! $host) {
            return null;
        }

        $domain = NewsDomain::query()
            ->where('is_active', true)
            ->whereNotNull('media_outlet_id')
            ->with('mediaOutlet')
            ->get()
            ->filter(fn (NewsDomain $domain): bool => $host === $domain->domain || str_ends_with($host, '.'.$domain->domain))
            ->sortByDesc(fn (NewsDomain $domain): int => strlen($domain->domain))
            ->first();

        return $domain?->mediaOutlet;
    }
}
