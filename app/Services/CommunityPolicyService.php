<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class CommunityPolicyService
{
    public function all(): array
    {
        return Cache::store(config('truthshield.status_cache_store'))->remember('community:policy:v1', now()->addSeconds(30), function (): array {
            $overrides = SystemSetting::query()->where('key', 'community_policy')->value('value') ?: [];

            return [
                'min_distinct_users' => (int) ($overrides['min_distinct_users'] ?? config('truthshield_community.min_distinct_users', 3)),
                'trust_floor' => (float) ($overrides['trust_floor'] ?? config('truthshield_community.trust_floor', 1.0)),
                'thresholds' => [
                    ...config('truthshield_community.thresholds', []),
                    ...($overrides['thresholds'] ?? []),
                ],
                'high_risk_source_types' => array_values(array_unique([
                    ...config('truthshield_community.high_risk_source_types', []),
                    ...($overrides['high_risk_source_types'] ?? []),
                ])),
                'high_risk_domain_keywords' => array_values(array_unique([
                    ...config('truthshield_community.high_risk_domain_keywords', []),
                    ...($overrides['high_risk_domain_keywords'] ?? []),
                ])),
                'task_stale_days' => (int) ($overrides['task_stale_days'] ?? config('truthshield_community.task_stale_days', 14)),
            ];
        });
    }

    public function threshold(string $key, float $default = 0): float
    {
        return (float) ($this->all()['thresholds'][$key] ?? $default);
    }

    public function minDistinctUsers(): int
    {
        return (int) $this->all()['min_distinct_users'];
    }

    public function trustFloor(): float
    {
        return (float) $this->all()['trust_floor'];
    }
}
