<?php

namespace Tests\Feature;

use App\Models\NewsUrl;
use App\Services\UrlFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsStatusCacheObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalized_status_is_reported_as_an_optimized_snapshot_hit(): void
    {
        config([
            'truthshield.status_cache_store' => 'array',
            'truthshield_traffic.enabled' => true,
            'truthshield_traffic.record_api_requests' => true,
            'truthshield_traffic.status_sample_rate' => 1,
        ]);

        $url = 'https://www.cna.com.tw/news/aipl/202606190001.aspx';
        $fingerprint = app(UrlFingerprintService::class)->fingerprint($url);

        NewsUrl::query()->create([
            'hash' => $fingerprint['hash'],
            'original_url' => $url,
            'normalized_url' => $fingerprint['normalized_url'],
            'voting_closes_at' => now()->subDay(),
            'finalized_at' => now()->subHour(),
            'algorithm_version' => 'truthshield-v1',
            'final_status_payload' => [
                'url_hash' => $fingerprint['hash'],
                'normalized_url' => $fingerprint['normalized_url'],
                'top_tag' => null,
                'distribution' => [],
                'secondary_distribution' => [],
                'percentage' => 0,
                'total_weight' => 0,
                'is_open' => false,
                'voting_closes_at' => now()->subDay()->toIso8601String(),
                'finalized_at' => now()->subHour()->toIso8601String(),
                'algorithm_version' => 'truthshield-v1',
            ],
            'final_evidence_payload' => [],
        ]);

        $this->getJson('/api/news/status?url='.urlencode($url))
            ->assertOk()
            ->assertHeader('X-TruthShield-Cache', 'snapshot')
            ->assertJsonPath('cache_status', 'snapshot');

        $this->getJson('/api/traffic/summary')
            ->assertOk()
            ->assertJsonPath('cache_hit_rate', 100);
    }
}
