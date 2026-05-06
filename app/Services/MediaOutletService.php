<?php

namespace App\Services;

use App\Models\NewsDomain;
use App\Models\NewsUrl;

class MediaOutletService
{
    public function attachOutlet(NewsUrl $newsUrl): void
    {
        if ($newsUrl->media_outlet_id) {
            return;
        }

        $host = strtolower((string) parse_url($newsUrl->normalized_url, PHP_URL_HOST));
        if (! $host) {
            return;
        }

        $domain = NewsDomain::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (NewsDomain $domain): bool => $host === $domain->domain || str_ends_with($host, '.' . $domain->domain))
            ->sortByDesc(fn (NewsDomain $domain): int => strlen($domain->domain))
            ->first();

        if ($domain?->media_outlet_id) {
            $newsUrl->forceFill(['media_outlet_id' => $domain->media_outlet_id])->save();
        }
    }
}
