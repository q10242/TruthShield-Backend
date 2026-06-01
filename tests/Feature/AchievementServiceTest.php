<?php

namespace Tests\Feature;

use App\Models\NewsUrl;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vote;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_achievement_definitions_have_progressive_tiers(): void
    {
        $definitions = collect(app(AchievementService::class)->definitions());

        $this->assertGreaterThanOrEqual(120, $definitions->count());
        $this->assertSame(
            [1, 3, 10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000],
            $definitions->where('metric', 'votes')->pluck('target')->values()->all(),
        );
        foreach ($definitions->groupBy('metric') as $metric => $items) {
            $this->assertSame(10000, $items->max('target'), "Metric {$metric} should continue to 10,000.");
        }
        $this->assertSame(10000, $definitions->firstWhere('slug', 'ten-thousand-sources')['target']);
        $this->assertSame(10000, $definitions->firstWhere('slug', 'ten-thousand-community-signals')['target']);
        $this->assertSame(10000, $definitions->firstWhere('slug', 'ten-thousand-articles')['target']);
    }

    public function test_profile_keeps_showing_next_stage_after_unlocking_vote_badges(): void
    {
        $user = User::factory()->create();
        $tag = Tag::query()->create([
            'name' => '事實準確',
            'slug' => 'accurate-reporting',
            'color' => '#22c55e',
            'severity' => 'positive',
            'requires_evidence' => false,
        ]);

        foreach (range(1, 10) as $index) {
            $newsUrl = NewsUrl::query()->create([
                'hash' => "achievement-test-{$index}",
                'original_url' => "https://news.example.test/achievement-{$index}",
                'normalized_url' => "https://news.example.test/achievement-{$index}",
            ]);

            Vote::query()->create([
                'user_id' => $user->id,
                'news_url_id' => $newsUrl->id,
                'tag_id' => $tag->id,
                'weight_score' => 1.0,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('achievement_summary.unlocked_count', 3);

        $achievements = collect($response->json('achievements'));

        $this->assertTrue($achievements->firstWhere('slug', 'steady-reviewer')['unlocked']);
        $this->assertFalse($achievements->firstWhere('slug', 'senior-reviewer')['unlocked']);
        $this->assertSame(10, $achievements->firstWhere('slug', 'senior-reviewer')['current']);
        $this->assertSame(25, $achievements->firstWhere('slug', 'senior-reviewer')['target']);
    }
}
