<?php

namespace Tests\Feature;

use App\Models\CommunityTask;
use App\Models\Journalist;
use App\Models\JournalistAlias;
use App\Models\JournalistNewsUrl;
use App\Models\MediaOutlet;
use App\Models\NewsUrl;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportLabelStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_outlet_stats_count_effective_clickbait_labels(): void
    {
        [$media, $clickbait, $accurate] = $this->seedBasics();

        for ($i = 0; $i < 8; $i++) {
            $this->createNewsWithVotes($media, $clickbait, 1);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->createNewsWithVotes($media, $accurate, 1);
        }

        $response = $this->getJson("/api/media-outlets/{$media->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.article_count', 10)
            ->assertJsonPath('data.tracked_tag_count', 8)
            ->assertJsonPath('data.tracked_tag_ratio', 80);
    }

    public function test_journalist_stats_exclude_suspected_matches(): void
    {
        [$media, $clickbait, $accurate] = $this->seedBasics();
        $journalist = Journalist::query()->create([
            'media_outlet_id' => $media->id,
            'display_name' => '王小明',
            'canonical_name' => '王小明',
            'status' => 'active',
        ]);

        $confirmed = $this->createNewsWithVotes($media, $clickbait, 10);
        $suspected = $this->createNewsWithVotes($media, $accurate, 1);

        JournalistNewsUrl::query()->create([
            'journalist_id' => $journalist->id,
            'news_url_id' => $confirmed->id,
            'match_source' => 'selector',
            'confidence' => 'high',
            'review_status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        JournalistNewsUrl::query()->create([
            'journalist_id' => $journalist->id,
            'news_url_id' => $suspected->id,
            'match_source' => 'full_text',
            'confidence' => 'low',
            'review_status' => 'suspected',
        ]);

        $response = $this->getJson("/api/journalists/{$journalist->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.article_count', 1)
            ->assertJsonPath('data.tracked_tag_count', 1)
            ->assertJsonPath('data.tracked_tag_ratio', null);
    }

    public function test_journalist_cache_contains_aliases_and_exclusions_shape(): void
    {
        [$media] = $this->seedBasics();
        $journalist = Journalist::query()->create([
            'media_outlet_id' => $media->id,
            'display_name' => '王小明',
            'canonical_name' => '王小明',
            'status' => 'active',
        ]);
        JournalistAlias::query()->create([
            'journalist_id' => $journalist->id,
            'alias' => '記者王小明',
            'domain' => 'example.test',
            'confidence' => 'high',
        ]);

        $response = $this->getJson('/api/journalists/cache');

        $response->assertOk()
            ->assertJsonPath('journalists.0.display_name', '王小明')
            ->assertJsonPath('journalists.0.aliases.0.alias', '記者王小明')
            ->assertJsonStructure(['version', 'updated_at', 'ttl_seconds', 'journalists', 'exclusions']);
    }

    public function test_match_report_marks_reported_and_creates_review_task(): void
    {
        [$media, $clickbait] = $this->seedBasics();
        $journalist = Journalist::query()->create([
            'media_outlet_id' => $media->id,
            'display_name' => '王小明',
            'canonical_name' => '王小明',
            'status' => 'active',
        ]);
        $news = $this->createNewsWithVotes($media, $clickbait, 1);
        $match = JournalistNewsUrl::query()->create([
            'journalist_id' => $journalist->id,
            'news_url_id' => $news->id,
            'match_source' => 'selector',
            'confidence' => 'high',
            'review_status' => 'confirmed',
        ]);

        $response = $this->postJson("/api/news/journalist-matches/{$match->id}/report", [
            'reason' => '作者欄不是這位記者',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.review_status', 'reported');
        $this->assertDatabaseHas('community_tasks', [
            'type' => 'journalist_match_review',
            'subject_id' => $match->id,
            'status' => 'open',
        ]);
    }

    private function seedBasics(): array
    {
        $media = MediaOutlet::query()->create([
            'name' => '測試媒體',
            'slug' => 'test-media',
            'type' => 'news',
            'region' => 'TW',
            'is_active' => true,
        ]);
        $clickbait = Tag::query()->create([
            'name' => '標題殺人',
            'slug' => 'clickbait-title',
            'color' => '#ef4444',
            'severity' => 'high',
            'requires_evidence' => true,
        ]);
        $accurate = Tag::query()->create([
            'name' => '事實準確',
            'slug' => 'accurate-reporting',
            'color' => '#22c55e',
            'severity' => 'positive',
            'requires_evidence' => false,
        ]);

        return [$media, $clickbait, $accurate];
    }

    private function createNewsWithVotes(MediaOutlet $media, Tag $tag, int $count): NewsUrl
    {
        $news = NewsUrl::query()->create([
            'hash' => sha1($media->slug . '|' . $tag->slug . '|' . uniqid('', true)),
            'media_outlet_id' => $media->id,
            'original_url' => 'https://example.test/news/' . uniqid(),
            'normalized_url' => 'https://example.test/news/' . uniqid(),
            'title_snapshot' => '測試新聞',
            'voting_closes_at' => now()->addHours(72),
        ]);

        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create();
            Vote::query()->create([
                'user_id' => $user->id,
                'news_url_id' => $news->id,
                'tag_id' => $tag->id,
                'weight_score' => 1.0,
                'hidden' => false,
            ]);
        }

        return $news;
    }
}
