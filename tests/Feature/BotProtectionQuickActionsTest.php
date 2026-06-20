<?php

namespace Tests\Feature;

use App\Models\NewsUrl;
use App\Models\ReadSession;
use App\Models\Tag;
use App\Models\TrustScoreHistory;
use App\Models\User;
use App\Services\BotTrustRecoveryService;
use App\Services\BotProtectionService;
use App\Services\TurnstileService;
use App\Services\UrlFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotProtectionQuickActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'truthshield.status_cache_store' => 'array',
            'truthshield.min_read_seconds_before_vote' => 15,
            'truthshield_bot.enabled' => true,
            'truthshield_bot.turnstile_enabled' => true,
            'truthshield_bot.turnstile_secret' => 'test-secret',
            'truthshield_bot.challenge_mode' => 'always',
            'truthshield_bot.block_threshold' => 100,
            'truthshield_bot.expected_hostname' => 'truth-shield.otus.tw',
            'truthshield_bot.fallback_per_minute' => 3,
            'truthshield_bot.fallback_per_hour' => 30,
        ]);
        Cache::store('array')->flush();
    }

    public function test_reader_reaction_requires_challenge_and_read_time(): void
    {
        $user = User::factory()->create();
        $url = 'https://news.example.test/quick-reaction';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reactions', [
                'news_url' => $url,
                'feelings' => ['clear'],
            ])
            ->assertStatus(428)
            ->assertJsonPath('bot_protection.challenge_required', true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reactions', [
                'news_url' => $url,
                'feelings' => ['clear'],
                'challenge_token' => 'local-pass',
            ])
            ->assertStatus(428)
            ->assertJsonPath('minimum_read_seconds', 15);

        $newsUrl = $this->newsUrl($url);
        ReadSession::query()->create([
            'user_id' => $user->id,
            'news_url_id' => $newsUrl->id,
            'seconds_read' => 15,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reactions', [
                'news_url' => $url,
                'feelings' => ['clear'],
                'challenge_token' => 'local-pass',
            ])
            ->assertCreated()
            ->assertJsonPath('reaction.feelings.0', 'clear');
    }

    public function test_retried_invalid_challenges_deduct_trust_with_a_daily_cap(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']], 200),
        ]);
        $user = User::factory()->create(['trust_score' => 1.0]);

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $request = Request::create('/api/vote', 'POST', [
                'challenge_token' => 'invalid-token',
                'challenge_retry' => true,
            ]);
            $request->setUserResolver(fn () => $user);
            $response = app(BotProtectionService::class)->enforce($request, 'vote.create');
            $this->assertSame(428, $response?->status());
        }

        $this->assertSame(0.8, round((float) $user->refresh()->trust_score, 2));
        $this->assertCount(4, TrustScoreHistory::query()
            ->where('user_id', $user->id)
            ->where('reason', 'like', 'bot_challenge_failure:%')
            ->get());
    }

    public function test_provider_outage_fails_open_then_applies_fallback_rate_limit(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $user = User::factory()->create();
        $tag = Tag::query()->create([
            'name' => '來源清楚',
            'slug' => 'clear-source-test',
            'severity' => 'positive',
            'requires_evidence' => false,
        ]);
        $url = 'https://news.example.test/provider-outage';
        $newsUrl = $this->newsUrl($url);
        ReadSession::query()->create([
            'user_id' => $user->id,
            'news_url_id' => $newsUrl->id,
            'seconds_read' => 15,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/vote', [
                    'url' => $url,
                    'tag_id' => $tag->id,
                    'challenge_token' => 'provider-fail',
                ])
                ->assertCreated();
        }

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'challenge_token' => 'provider-fail',
            ])
            ->assertStatus(429)
            ->assertJsonPath('bot_protection.provider_unavailable', true);

        $this->assertSame(1.0, (float) $user->refresh()->trust_score);
    }

    public function test_old_challenge_deduction_is_restored_after_clean_window(): void
    {
        $user = User::factory()->create(['trust_score' => 0.95]);
        $history = TrustScoreHistory::query()->create([
            'user_id' => $user->id,
            'previous_score' => 1.0,
            'delta' => -0.05,
            'new_score' => 0.95,
            'reason' => 'bot_challenge_failure:20260501:5',
            'details' => 'test deduction',
        ]);
        $history->forceFill(['created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)])->save();

        $this->assertSame(1, app(BotTrustRecoveryService::class)->recoverEligible());
        $this->assertSame(1.0, round((float) $user->refresh()->trust_score, 2));
        $this->assertDatabaseHas('trust_score_histories', [
            'user_id' => $user->id,
            'reason' => "bot_challenge_recovery:{$history->id}",
        ]);
        $this->assertSame(0, app(BotTrustRecoveryService::class)->recoverEligible());
    }

    public function test_public_config_exposes_challenge_mode_and_fallback_limits(): void
    {
        $this->getJson('/api/bot/config')
            ->assertOk()
            ->assertJsonPath('challenge_mode', 'always')
            ->assertJsonPath('fallback_limits.per_minute', 3)
            ->assertJsonPath('fallback_limits.per_hour', 30);
    }

    public function test_turnstile_validates_normalized_action_and_token_age(): void
    {
        Http::fakeSequence()
            ->push([
                'success' => true,
                'hostname' => 'truth-shield.otus.tw',
                'action' => 'reader_reaction',
                'challenge_ts' => now()->toIso8601String(),
            ])
            ->push([
                'success' => true,
                'hostname' => 'truth-shield.otus.tw',
                'action' => 'reader_reaction',
                'challenge_ts' => now()->subMinutes(6)->toIso8601String(),
            ]);

        $request = Request::create('/api/reactions', 'POST');
        $service = app(TurnstileService::class);

        $this->assertSame('success', $service->verify('fresh-token', $request, 'reader.reaction')['status']);
        $this->assertSame('invalid', $service->verify('stale-token', $request, 'reader.reaction')['status']);
    }

    private function newsUrl(string $url): NewsUrl
    {
        $fingerprint = app(UrlFingerprintService::class)->fingerprint($url);

        return NewsUrl::query()->firstOrCreate(
            ['hash' => $fingerprint['hash']],
            [
                'original_url' => $url,
                'normalized_url' => $fingerprint['normalized_url'],
                'voting_closes_at' => now()->addHours(72),
            ],
        );
    }
}
