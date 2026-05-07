<?php

namespace Tests\Feature;

use App\Models\NewsUrl;
use App\Models\AccountEdge;
use App\Models\AccountSignal;
use App\Models\ApiClient;
use App\Models\Appeal;
use App\Models\NewsDomain;
use App\Models\NewsDomainReport;
use App\Models\NewsChangeReport;
use App\Models\NewsUrlSnapshot;
use App\Models\MediaOutlet;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vote;
use App\Models\Badge;
use App\Models\BugReport;
use App\Models\Donation;
use App\Models\AbuseEvent;
use App\Models\AbuseCluster;
use App\Models\AlgorithmVersion;
use App\Models\CommunitySignal;
use App\Models\CommunityTask;
use App\Models\EvidenceReport;
use App\Models\Evidence;
use App\Models\EvidenceSnapshot;
use App\Models\ExtensionSelectorCheck;
use App\Models\ExtensionEvent;
use App\Models\OperationalEvent;
use App\Models\OfficialResponse;
use App\Models\OfficialResponseReaction;
use App\Models\RateLimitPolicy;
use App\Models\SystemSetting;
use App\Models\TrustedEvidenceSource;
use App\Models\TrustedSourceSuggestion;
use App\Models\UrlClassificationReport;
use App\Models\UserIdentity;
use App\Models\VerifiedClaimant;
use App\Models\TrustSettlement;
use App\Models\UserNotification;
use App\Jobs\FinalizeNewsUrlJob;
use App\Jobs\SnapshotEvidenceJob;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TruthShieldApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['truthshield.status_cache_store' => 'array']);
        config(['truthshield.min_read_seconds_before_vote' => 0]);
        config(['truthshield_bot.enabled' => false]);
        config(['truthshield_bot.turnstile_enabled' => false]);
        config(['truthshield_bot.challenge_threshold' => 50]);
        config(['truthshield_bot.block_threshold' => 90]);
    }

    public function test_news_domains_response_shape(): void
    {
        NewsDomain::query()->create([
            'domain' => 'active-news.test',
            'is_active' => true,
            'title_selector' => 'h1',
            'content_selector' => 'article',
            'blocked_path_pattern' => '^/video',
        ]);
        NewsDomain::query()->create([
            'domain' => 'inactive-news.test',
            'is_active' => false,
        ]);

        $this->getJson('/api/news-domains')
            ->assertOk()
            ->assertJsonFragment(['domain' => 'active-news.test'])
            ->assertJsonFragment(['title_selector' => 'h1'])
            ->assertJsonMissing(['domain' => 'inactive-news.test'])
            ->assertJsonStructure([
                'data' => [
                    ['domain'],
                ],
            ]);
    }

    public function test_tags_response_shape(): void
    {
        $this->seed(TagSeeder::class);

        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'slug', 'color', 'severity', 'requires_evidence', 'description'],
                ],
            ]);
    }

    public function test_news_domain_report_can_be_submitted(): void
    {
        $this->postJson('/api/news-domain-reports', [
            'url' => 'https://example-news.test/politics/story-1',
            'page_title' => 'Example News Story',
            'note' => '這是一個未收錄的新聞網站。',
        ])
            ->assertCreated()
            ->assertJsonPath('report.domain', 'example-news.test')
            ->assertJsonPath('report.status', 'pending');

        $this->assertDatabaseHas((new NewsDomainReport())->getTable(), [
            'domain' => 'example-news.test',
            'status' => 'pending',
        ]);
    }

    public function test_vote_requires_authentication(): void
    {
        $this->seed(TagSeeder::class);
        $tag = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();

        $this->postJson('/api/vote', [
            'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx',
            'tag_id' => $tag->id,
        ])->assertUnauthorized();
    }

    public function test_negative_vote_requires_evidence_url(): void
    {
        $this->seed(TagSeeder::class);
        $user = User::factory()->create();
        $tag = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx',
                'tag_id' => $tag->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('evidence_url');
    }

    public function test_negative_vote_requires_evidence_note(): void
    {
        $this->seed(TagSeeder::class);
        $user = User::factory()->create();
        $tag = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx',
                'tag_id' => $tag->id,
                'evidence_url' => 'https://example.com/source',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('evidence_note');
    }

    public function test_valid_token_vote_records_weight_and_clears_status_cache(): void
    {
        $this->seed(TagSeeder::class);
        $user = User::factory()->create(['trust_score' => 2.5]);
        $tag = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();
        $token = $user->createToken('test', ['vote'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx?b=2&a=1',
                'tag_id' => $tag->id,
                'evidence_url' => 'https://imgur.com/a/example',
                'evidence_note' => '標題省略關鍵事實，原始資料顯示並非單一因素造成。',
            ])
            ->assertCreated()
            ->assertJsonPath('vote.weight_score', 2);

        $newsUrl = NewsUrl::query()->firstOrFail();

        $this->assertSame(
            'https://www.cna.com.tw/news/aipl/202605060001.aspx?a=1&b=2',
            $newsUrl->normalized_url,
        );
        $this->assertNotNull($newsUrl->voting_closes_at);
        $this->assertTrue($newsUrl->created_at->copy()->addHours(72)->equalTo($newsUrl->voting_closes_at));

        $cacheKey = 'news:status:' . config('truthshield.status_cache_version', 'v1') . ':' . $newsUrl->hash;
        Cache::store(config('truthshield.status_cache_store'))->put($cacheKey, ['stale' => true], now()->addMinutes(5));

        $this->withToken($token)
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx?a=1&b=2',
                'tag_id' => $tag->id,
                'evidence_url' => 'https://i.imgur.com/example.png',
                'evidence_note' => '截圖標出標題與內文落差。',
            ])
            ->assertCreated();

        $this->assertFalse(Cache::store(config('truthshield.status_cache_store'))->has($cacheKey));
    }

    public function test_status_response_includes_display_fields(): void
    {
        $this->seed(TagSeeder::class);

        $this->getJson('/api/news/status?url=' . urlencode('https://www.cna.com.tw/news/aipl/202605060001.aspx'))
            ->assertOk()
            ->assertJsonPath('top_tag', null)
            ->assertJsonPath('display_text', '尚無足夠投票資料')
            ->assertJsonPath('tone', 'neutral')
            ->assertJsonStructure([
                'is_open',
                'voting_closes_at',
                'finalized_at',
            ]);
    }

    public function test_news_snapshot_records_metadata_and_detects_changes(): void
    {
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->postJson('/api/news/snapshot', [
            'url' => $url,
            'title_snapshot' => '原始標題',
            'canonical_url' => $url,
            'description' => '原始摘要',
            'image_url' => 'https://www.cna.com.tw/og.jpg',
            'content_hash' => 'abc123',
        ])
            ->assertCreated()
            ->assertJsonPath('snapshot.snapshot_type', 'initial')
            ->assertJsonPath('status.snapshot.availability_status', 'available');

        $this->postJson('/api/news/snapshot', [
            'url' => $url,
            'title_snapshot' => '修改後標題',
            'canonical_url' => $url,
            'description' => '原始摘要',
            'image_url' => 'https://www.cna.com.tw/og.jpg',
            'content_hash' => 'def456',
        ])
            ->assertCreated()
            ->assertJsonPath('snapshot.snapshot_type', 'changed')
            ->assertJsonPath('status.snapshot.changed_snapshots_count', 1);

        $newsUrl = NewsUrl::query()->firstOrFail();

        $this->assertDatabaseHas((new NewsUrlSnapshot())->getTable(), [
            'news_url_id' => $newsUrl->id,
            'snapshot_type' => 'changed',
        ]);

        $this->getJson('/api/news/status?url=' . urlencode($url))
            ->assertOk()
            ->assertJsonPath('snapshot.latest_snapshot.snapshot_type', 'changed');
    }

    public function test_news_change_report_can_mark_deleted_article_for_review(): void
    {
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->postJson('/api/news/change-reports', [
            'url' => $url,
            'report_type' => 'deleted',
            'page_title' => '找不到頁面',
            'note' => '原新聞連結已經無法開啟。',
        ])
            ->assertCreated()
            ->assertJsonPath('report.report_type', 'deleted')
            ->assertJsonPath('report.status', 'pending');

        $this->assertDatabaseHas((new NewsChangeReport())->getTable(), [
            'report_type' => 'deleted',
            'status' => 'pending',
        ]);

        $this->getJson('/api/news/status?url=' . urlencode($url))
            ->assertOk()
            ->assertJsonPath('snapshot.pending_change_reports_count', 1);
    }

    public function test_user_has_one_editable_vote_per_news_url(): void
    {
        $this->seed(TagSeeder::class);
        $user = User::factory()->create(['trust_score' => 1.4]);
        $accurate = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();
        $clickbait = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $accurate->id,
            ])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $clickbait->id,
                'evidence_url' => 'https://imgur.com/a/example',
                'evidence_note' => '標題和內文的前提不一致。',
            ])
            ->assertCreated()
            ->assertJsonPath('vote.tag_id', $clickbait->id);

        $newsUrl = NewsUrl::query()->firstOrFail();
        $this->assertSame(1, $newsUrl->votes()->count());
        $this->assertSame($clickbait->id, $newsUrl->votes()->firstOrFail()->tag_id);
    }

    public function test_vote_is_rejected_after_voting_window_closes(): void
    {
        $this->seed(TagSeeder::class);
        $user = User::factory()->create();
        $tag = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
            ])
            ->assertCreated();

        $newsUrl = NewsUrl::query()->firstOrFail();
        $newsUrl->forceFill(['voting_closes_at' => now()->subMinute()])->save();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('status.is_open', false);

        $this->assertSame(1, $newsUrl->votes()->count());
    }

    public function test_closed_status_finalizes_once_and_uses_snapshot(): void
    {
        $this->seed(TagSeeder::class);
        $user = User::factory()->create(['trust_score' => 2]);
        $lateUser = User::factory()->create(['trust_score' => 99]);
        $accurate = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();
        $clickbait = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $accurate->id,
            ])
            ->assertCreated();

        $newsUrl = NewsUrl::query()->firstOrFail();
        $newsUrl->forceFill(['voting_closes_at' => now()->subMinute()])->save();

        $this->getJson('/api/news/status?url=' . urlencode($url))
            ->assertOk()
            ->assertJsonPath('is_open', false)
            ->assertJsonPath('top_tag.id', $accurate->id)
            ->assertJsonPath('total_weight', 2);

        $newsUrl->refresh();
        $this->assertNotNull($newsUrl->finalized_at);
        $this->assertIsArray($newsUrl->final_status_payload);

        $newsUrl->votes()->create([
            'user_id' => $lateUser->id,
            'tag_id' => $clickbait->id,
            'evidence_url' => 'https://imgur.com/a/late',
            'evidence_type' => 'image',
            'evidence_note' => '這筆資料是定案後直接插入，不能影響快照。',
            'weight_score' => 99,
        ]);

        $this->getJson('/api/news/status?url=' . urlencode($url))
            ->assertOk()
            ->assertJsonPath('top_tag.id', $accurate->id)
            ->assertJsonPath('total_weight', 2);
    }

    public function test_evidence_can_be_listed_and_reacted_to_with_weight(): void
    {
        $this->seed(TagSeeder::class);
        $author = User::factory()->create(['trust_score' => 1.7]);
        $reviewer = User::factory()->create(['trust_score' => 2.2]);
        $tag = Tag::query()->where('slug', 'out-of-context')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://example.com/context-report',
                'evidence_note' => '相關報導提供完整時間線。',
            ])
            ->assertCreated();

        $vote = NewsUrl::query()->firstOrFail()->votes()->firstOrFail();

        $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/evidence/{$vote->id}/reaction", ['helpful' => true])
            ->assertOk()
            ->assertJsonPath('reaction.weight_score', 2.2);

        $this->getJson('/api/news/evidence?url=' . urlencode($url))
            ->assertOk()
            ->assertJsonPath('data.0.evidence_note', '相關報導提供完整時間線。')
            ->assertJsonPath('data.0.helpful_weight', 2.2)
            ->assertJsonPath('data.0.evidence_host', 'example.com')
            ->assertJsonPath('data.0.evidence_safety', 'unverified')
            ->assertJsonStructure([
                'data' => [
                    ['evidence_host', 'evidence_safety', 'is_trusted_evidence', 'preview_url'],
                ],
            ]);
    }

    public function test_evidence_url_blocks_private_hosts_and_marks_trusted_hosts(): void
    {
        config(['truthshield.trusted_evidence_hosts' => ['i.imgur.com']]);

        $this->seed(TagSeeder::class);
        $user = User::factory()->create();
        $tag = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'http://127.0.0.1/private.png',
                'evidence_note' => '不應允許私網證據。',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('evidence_url');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://i.imgur.com/example.png',
                'evidence_note' => '可信圖床截圖。',
            ])
            ->assertCreated()
            ->assertJsonPath('vote.evidence_host', 'i.imgur.com')
            ->assertJsonPath('vote.evidence_safety', 'trusted');
    }

    public function test_cloud_drive_evidence_url_is_supported_without_mirroring_images(): void
    {
        config([
            'truthshield.trusted_evidence_hosts' => [],
            'truthshield.cloud_drive_evidence_hosts' => ['drive.google.com', 'www.dropbox.com'],
        ]);

        $this->seed(TagSeeder::class);
        $user = User::factory()->create();
        $tag = Tag::query()->where('slug', 'out-of-context')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://drive.google.com/file/d/example/view?usp=sharing',
                'evidence_note' => '雲端硬碟保存的截圖證據。',
            ])
            ->assertCreated()
            ->assertJsonPath('vote.evidence_host', 'drive.google.com')
            ->assertJsonPath('vote.evidence_type', 'cloud_drive')
            ->assertJsonPath('vote.evidence_safety', 'unverified');

        $vote = Vote::query()->findOrFail($response->json('vote.id'));
        $this->assertSame('cloud_drive', $vote->evidence->type);
        $this->assertNull($vote->evidence->archive_url);

        $this->artisan('truthshield:snapshot-evidence --limit=10')->assertExitCode(0);

        $this->assertSame('external', $vote->evidence->refresh()->snapshot_status);
        $this->assertDatabaseHas('evidence_snapshots', [
            'evidence_id' => $vote->evidence->id,
            'status' => 'external',
        ]);
    }

    public function test_low_trust_user_cannot_rate_evidence(): void
    {
        config(['truthshield.evidence_reaction_min_trust_score' => 0.5]);

        $this->seed(TagSeeder::class);
        $author = User::factory()->create(['trust_score' => 1.7]);
        $reviewer = User::factory()->create(['trust_score' => 0.49]);
        $tag = Tag::query()->where('slug', 'out-of-context')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://example.com/context-report',
                'evidence_note' => '相關報導提供完整時間線。',
            ])
            ->assertCreated();

        $vote = NewsUrl::query()->firstOrFail()->votes()->firstOrFail();

        $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/evidence/{$vote->id}/reaction", ['helpful' => true])
            ->assertForbidden()
            ->assertJsonPath('minimum_trust_score', 0.5)
            ->assertJsonPath('trust_score', 0.49);

        $this->assertSame(0, $vote->reactions()->count());
    }

    public function test_user_response_includes_evidence_reaction_permission(): void
    {
        config(['truthshield.evidence_reaction_min_trust_score' => 0.5]);

        $user = User::factory()->create(['trust_score' => 0.49]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('can_react_to_evidence', false)
            ->assertJsonPath('evidence_reaction_min_trust_score', 0.5)
            ->assertJsonStructure(['min_read_seconds_before_vote']);
    }

    public function test_vote_requires_read_session_when_configured(): void
    {
        config(['truthshield.min_read_seconds_before_vote' => 10]);

        $this->seed(TagSeeder::class);
        $user = User::factory()->create();
        $tag = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
            ])
            ->assertStatus(428)
            ->assertJsonPath('minimum_read_seconds', 10)
            ->assertJsonPath('seconds_read', 0);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/news/read-session', [
                'url' => $url,
                'seconds_read' => 10,
                'title_snapshot' => '閱讀門檻測試',
            ])
            ->assertOk()
            ->assertJsonPath('can_vote', true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
            ])
            ->assertCreated();
    }

    public function test_admin_user_can_access_filament_panel(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@truthshield.local'],
            User::factory()->make(['email' => 'admin@truthshield.local', 'is_admin' => true])->makeVisible('password')->toArray(),
        );

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_evidence_reaction_is_rejected_after_voting_window_closes(): void
    {
        $this->seed(TagSeeder::class);
        $author = User::factory()->create();
        $reviewer = User::factory()->create(['trust_score' => 1]);
        $tag = Tag::query()->where('slug', 'out-of-context')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://example.com/context-report',
                'evidence_note' => '相關報導提供完整時間線。',
            ])
            ->assertCreated();

        $newsUrl = NewsUrl::query()->firstOrFail();
        $vote = $newsUrl->votes()->firstOrFail();
        $newsUrl->forceFill(['voting_closes_at' => now()->subMinute()])->save();

        $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/evidence/{$vote->id}/reaction", ['helpful' => true])
            ->assertStatus(409)
            ->assertJsonPath('status.is_open', false);
    }

    public function test_authenticated_user_can_fetch_their_vote_for_url(): void
    {
        $this->seed(TagSeeder::class);
        $user = User::factory()->create();
        $tag = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', ['url' => $url, 'tag_id' => $tag->id])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/vote?url=' . urlencode($url))
            ->assertOk()
            ->assertJsonPath('vote.tag_id', $tag->id);
    }

    public function test_vote_writes_audit_log_and_low_trust_weight_is_capped(): void
    {
        config([
            'truthshield.evidence_reaction_min_trust_score' => 0.5,
            'truthshield.low_trust_vote_cap' => 0.25,
        ]);

        $this->seed(TagSeeder::class);
        $user = User::factory()->create(['trust_score' => 0.49]);
        $tag = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx',
                'tag_id' => $tag->id,
            ])
            ->assertCreated()
            ->assertJsonPath('vote.weight_score', 0.25);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'vote.upserted',
        ]);
    }

    public function test_evidence_report_can_hide_evidence_through_api_and_library_excludes_hidden(): void
    {
        $this->seed(TagSeeder::class);
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $tag = Tag::query()->where('slug', 'out-of-context')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://example.com/context-report',
                'evidence_note' => '相關報導提供完整時間線。',
            ])
            ->assertCreated();

        $vote = Vote::query()->firstOrFail();

        $this->actingAs($reporter, 'sanctum')
            ->postJson("/api/evidence/{$vote->id}/report", [
                'reason' => 'personal_data',
                'note' => '可能包含個資。',
            ])
            ->assertCreated()
            ->assertJsonPath('report.status', 'pending');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'type' => 'evidence.reported',
        ]);

        $this->getJson('/api/evidence-library')
            ->assertOk()
            ->assertJsonPath('data.0.id', $vote->id)
            ->assertJsonPath('meta.total', 1);

        $vote->forceFill(['hidden' => true, 'moderation_status' => 'hidden'])->save();

        $this->getJson('/api/evidence-library')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_media_leaderboard_and_transparency_endpoints(): void
    {
        $this->seed(TagSeeder::class);
        $outlet = MediaOutlet::query()->create(['name' => 'CNA', 'slug' => 'cna', 'is_active' => true]);
        NewsDomain::query()->updateOrCreate(['domain' => 'www.cna.com.tw'], ['media_outlet_id' => $outlet->id, 'is_active' => true]);
        $user = User::factory()->create(['trust_score' => 2]);
        $tag = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx',
                'tag_id' => $tag->id,
            ])
            ->assertCreated();

        $this->getJson('/api/leaderboard/media')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'CNA')
            ->assertJsonPath('data.0.score', 100);

        $this->getJson('/api/transparency')
            ->assertOk()
            ->assertJsonStructure(['users', 'news_urls', 'votes', 'pending_domain_reports', 'open_abuse_events', 'open_bug_reports']);
    }

    public function test_profile_logout_health_search_media_and_report_reason_endpoints(): void
    {
        $this->seed(TagSeeder::class);
        $outlet = MediaOutlet::query()->create(['name' => 'CNA', 'slug' => 'cna', 'is_active' => true]);
        NewsDomain::query()->updateOrCreate(
            ['domain' => 'www.cna.com.tw'],
            ['media_outlet_id' => $outlet->id, 'is_active' => true, 'article_selector' => 'article.main-story', 'priority' => 5],
        );
        $user = User::factory()->create(['trust_score' => 2]);
        $tag = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();
        $token = $user->createToken('test', ['vote'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx',
                'tag_id' => $tag->id,
                'title_snapshot' => '中央社測試新聞',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('stats.votes', 1)
            ->assertJsonStructure(['recent_votes', 'notifications', 'badges']);

        $this->getJson('/api/news/search?q=' . urlencode('中央社'))
            ->assertOk()
            ->assertJsonPath('data.0.title_snapshot', '中央社測試新聞')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/media/cna')
            ->assertOk()
            ->assertJsonPath('media.slug', 'cna')
            ->assertJsonPath('recent_news.0.title_snapshot', '中央社測試新聞');

        $this->getJson('/api/evidence-report-reasons')
            ->assertOk()
            ->assertJsonPath('data.0.value', 'spam');

        $this->getJson('/api/news-domains')
            ->assertOk()
            ->assertJsonFragment([
                'domain' => 'www.cna.com.tw',
                'article_selector' => 'article.main-story',
                'priority' => 5,
            ]);

        $this->getJson('/api/system/health')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['counts', 'thresholds']);

        $this->withToken($token)
            ->getJson('/api/me/export')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure(['votes', 'evidence_reactions', 'trust_history', 'notifications', 'read_sessions']);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();
    }

    public function test_public_algorithm_docs_trust_leaderboard_news_detail_and_export_endpoints(): void
    {
        $this->seed(TagSeeder::class);
        $badge = Badge::query()->create(['name' => '測試徽章', 'slug' => 'test-badge']);
        $user = User::factory()->create(['name' => 'High Trust', 'trust_score' => 4.2]);
        $user->badges()->attach($badge->id, ['reason' => '測試']);
        $tag = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx',
                'tag_id' => $tag->id,
                'title_snapshot' => '新聞詳情測試',
            ])
            ->assertCreated();

        $newsUrl = NewsUrl::query()->firstOrFail();

        $this->getJson('/api/algorithm')
            ->assertOk()
            ->assertJsonStructure(['summary', 'principles', 'config']);

        $this->getJson('/api/docs')
            ->assertOk()
            ->assertJsonPath('name', 'TruthShield API')
            ->assertJsonPath('version', '0.9.0')
            ->assertJsonFragment(['path' => '/api/bug-reports']);

        $this->getJson('/api/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.title', 'TruthShield API');

        $this->getJson('/api/leaderboard/trust')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'High Trust')
            ->assertJsonPath('data.0.badges.0.slug', 'test-badge');

        $this->getJson("/api/news/by-id/{$newsUrl->id}")
            ->assertOk()
            ->assertJsonPath('news.title_snapshot', '新聞詳情測試')
            ->assertJsonPath('status.top_tag.id', $tag->id);

        $this->get('/api/exports/media.csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $this->get('/api/exports/news.csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $this->get('/api/exports/evidence.csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $this->get('/api/exports/bug-reports.csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $this->getJson('/api/extension/summary')
            ->assertOk()
            ->assertJsonStructure(['active_domains', 'votes', 'generated_at']);
    }

    public function test_oauth_callback_links_identity_and_vote_weight_uses_multipliers(): void
    {
        $this->seed(TagSeeder::class);
        $tag = Tag::query()->where('slug', 'accurate-reporting')->firstOrFail();

        $payload = $this->postJson('/api/auth/facebook/callback', [
            'provider_user_id' => 'fb-123',
            'email' => 'oauth@example.com',
            'name' => 'OAuth User',
        ])
            ->assertOk()
            ->assertJsonPath('user.identity_level', 'verified_social')
            ->json();

        $this->assertDatabaseHas('user_identities', [
            'provider' => 'facebook',
            'provider_user_id' => 'fb-123',
        ]);

        $user = User::query()->where('email', 'oauth@example.com')->firstOrFail();
        $user->forceFill(['trust_score' => 2, 'identity_multiplier' => 1.15, 'abuse_multiplier' => 0.5])->save();

        $this->withToken($payload['token'])
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605060001.aspx',
                'tag_id' => $tag->id,
            ])
            ->assertCreated()
            ->assertJsonPath('vote.weight_score', 1.15);
    }

    public function test_dev_login_can_be_disabled_for_production(): void
    {
        config(['truthshield.dev_login_enabled' => false]);

        $this->postJson('/api/auth/dev-login', [
            'email' => 'disabled@example.com',
            'name' => 'Disabled',
        ])
            ->assertForbidden();
    }

    public function test_bot_config_and_extension_nonce_are_available(): void
    {
        config(['truthshield_bot.enabled' => true]);

        $this->getJson('/api/bot/config')
            ->assertOk()
            ->assertJsonStructure([
                'bot_protection_enabled',
                'turnstile_enabled',
                'turnstile_site_key',
                'challenge_threshold',
                'protected_actions',
            ]);

        $this->getJson('/api/extension/nonce')
            ->assertOk()
            ->assertJsonStructure(['nonce', 'expires_at', 'signature']);
    }

    public function test_bot_challenge_can_gate_write_actions(): void
    {
        config([
            'truthshield.dev_login_enabled' => true,
            'truthshield_bot.enabled' => true,
            'truthshield_bot.turnstile_enabled' => true,
            'truthshield_bot.challenge_threshold' => 0,
            'truthshield_bot.block_threshold' => 100,
        ]);

        $this->postJson('/api/auth/dev-login', [
            'email' => 'challenge@example.com',
            'name' => 'Challenge',
        ])
            ->assertStatus(428)
            ->assertJsonPath('bot_protection.challenge_required', true);

        $this->postJson('/api/auth/dev-login', [
            'email' => 'challenge@example.com',
            'name' => 'Challenge',
            'challenge_token' => 'local-pass',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_profile_public_identity_claimant_and_official_response_flow(): void
    {
        $user = User::factory()->create(['trust_score' => 2, 'name' => 'Real Name']);
        $reviewer = User::factory()->create(['trust_score' => 2]);
        $newsUrl = NewsUrl::query()->create([
            'hash' => 'official-hash',
            'original_url' => 'https://www.cna.com.tw/news/aipl/202605070001.aspx',
            'normalized_url' => 'https://www.cna.com.tw/news/aipl/202605070001.aspx',
            'title_snapshot' => '測試新聞',
            'voting_closes_at' => now()->addHours(72),
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/profile', [
                'display_name' => '新聞當事人',
                'is_real_name_public' => false,
                'profile_bio' => '我會在必要時提供澄清。',
            ])
            ->assertOk()
            ->assertJsonPath('user.display_name', '新聞當事人')
            ->assertJsonPath('user.is_real_name_public', false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/me/claimants', [
                'claim_type' => 'subject',
                'domain' => 'cna.com.tw',
                'news_url_id' => $newsUrl->id,
                'proof_url' => 'https://drive.google.com/file/d/example/view',
                'statement' => '我是此新聞中的當事人，申請澄清身份。',
            ])
            ->assertCreated()
            ->assertJsonPath('claimant.status', 'pending');

        $claimant = VerifiedClaimant::query()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/official-responses', [
                'url' => $newsUrl->normalized_url,
                'verified_claimant_id' => $claimant->id,
                'response_type' => 'subject_clarification',
                'response_text' => '這則新聞省略了我的完整說法。',
                'evidence_url' => 'https://drive.google.com/file/d/clarification/view',
            ])
            ->assertForbidden();

        $claimant->forceFill(['status' => 'approved', 'verified_at' => now()])->save();
        $user->forceFill(['public_identity_label' => '當事人已驗證'])->save();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/official-responses', [
                'url' => $newsUrl->normalized_url,
                'verified_claimant_id' => $claimant->id,
                'response_type' => 'subject_clarification',
                'response_text' => '這則新聞省略了我的完整說法。',
                'evidence_url' => 'https://drive.google.com/file/d/clarification/view',
            ])
            ->assertCreated()
            ->assertJsonPath('official_response.status', 'pending');

        $response = OfficialResponse::query()->firstOrFail();
        $response->forceFill(['status' => 'published', 'published_at' => now()])->save();

        $this->getJson('/api/news/official-responses?url=' . urlencode($newsUrl->normalized_url))
            ->assertOk()
            ->assertJsonPath('data.0.author.display_name', '新聞當事人')
            ->assertJsonPath('data.0.author.identity_label', '當事人已驗證')
            ->assertJsonPath('data.0.response_text', '這則新聞省略了我的完整說法。');

        $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/official-responses/{$response->id}/reaction", ['helpful' => true])
            ->assertOk();

        $this->assertSame(1, OfficialResponseReaction::query()->count());
        $this->assertGreaterThan(0, (float) $response->fresh()->helpful_weight);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('verified_claimants.0.status', 'approved')
            ->assertJsonPath('official_responses.0.status', 'published');
    }

    public function test_extension_nonce_marks_signed_telemetry(): void
    {
        $nonce = $this->getJson('/api/extension/nonce')->assertOk()->json();

        $this->withHeaders([
            'X-TruthShield-Extension-Nonce' => $nonce['nonce'],
            'X-TruthShield-Extension-Signature' => $nonce['signature'],
        ])
            ->postJson('/api/extension/events', [
                'domain' => 'www.cna.com.tw',
                'event_type' => 'tooltip_shown',
                'success' => true,
                'extension_version' => '0.1.0',
            ])
            ->assertCreated();

        $event = ExtensionEvent::query()->firstOrFail();
        $this->assertTrue($event->metadata['extension_signature_valid']);
    }

    public function test_vote_creates_primary_evidence_and_status_includes_algorithm_version(): void
    {
        $this->seed(TagSeeder::class);
        $user = User::factory()->create(['trust_score' => 2]);
        $tag = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://i.imgur.com/example.png',
                'evidence_note' => '標題與內文明顯不一致。',
            ])
            ->assertCreated();

        $this->assertSame(1, Evidence::query()->count());
        $this->assertDatabaseHas('evidences', [
            'host' => 'i.imgur.com',
            'safety' => 'trusted',
        ]);

        $this->getJson('/api/news/status?url=' . urlencode($url))
            ->assertOk()
            ->assertJsonPath('algorithm_version', 'truthshield-v1');

        $this->getJson('/api/news/evidence?url=' . urlencode($url))
            ->assertOk()
            ->assertJsonStructure(['data' => [['quality_score', 'archive_url', 'snapshot_status']]]);
    }

    public function test_settlement_is_idempotent_and_snapshot_command_updates_evidence(): void
    {
        $this->seed(TagSeeder::class);
        $user = User::factory()->create(['trust_score' => 2]);
        $tag = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://i.imgur.com/settle.png',
                'evidence_note' => '定案測試證據。',
            ])
            ->assertCreated();

        $newsUrl = NewsUrl::query()->firstOrFail();
        $newsUrl->forceFill(['voting_closes_at' => now()->subMinute()])->save();

        $this->artisan('truthshield:finalize-news --settle')->assertExitCode(0);
        $this->artisan('truthshield:finalize-news --settle')->assertExitCode(0);

        $this->assertSame(1, TrustSettlement::query()->count());

        $this->artisan('truthshield:snapshot-evidence --limit=10')->assertExitCode(0);
        $this->assertSame(1, EvidenceSnapshot::query()->count());
        $this->assertDatabaseHas('evidences', ['snapshot_status' => 'snapshotted']);
    }

    public function test_appeals_moderation_events_and_extension_coverage_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/me/appeals', [
                'subject_type' => 'trust',
                'subject_id' => 1,
                'reason' => 'incorrect_penalty',
                'statement' => '我認為這次信用扣分需要重新檢視。',
            ])
            ->assertCreated()
            ->assertJsonPath('appeal.status', 'pending');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/appeals')
            ->assertOk()
            ->assertJsonPath('data.0.reason', 'incorrect_penalty');

        $this->getJson('/api/moderation-events')
            ->assertOk()
            ->assertJsonPath('data.0.event_type', 'appeal.created');

        $this->postJson('/api/extension/events', [
            'domain' => 'www.cna.com.tw',
            'event_type' => 'vote_panel_injected',
            'success' => true,
            'extension_version' => '0.1.0',
        ])
            ->assertCreated();

        $this->assertSame(1, ExtensionEvent::query()->count());

        $this->getJson('/api/extension/coverage')
            ->assertOk()
            ->assertJsonPath('data.0.domain', 'www.cna.com.tw')
            ->assertJsonPath('data.0.events.0.success_rate', 100);
    }

    public function test_algorithm_version_and_abuse_cluster_commands(): void
    {
        $user = User::factory()->create();
        $newsUrl = NewsUrl::query()->create([
            'hash' => 'cluster-hash',
            'original_url' => 'https://cluster.test/news/1',
            'normalized_url' => 'https://cluster.test/news/1',
            'voting_closes_at' => now()->addHours(72),
        ]);

        AbuseEvent::query()->create(['user_id' => $user->id, 'news_url_id' => $newsUrl->id, 'type' => 'rapid_user_votes', 'severity' => 'medium']);
        AbuseEvent::query()->create(['user_id' => $user->id, 'news_url_id' => $newsUrl->id, 'type' => 'rapid_user_votes', 'severity' => 'medium']);
        AbuseEvent::query()->create(['user_id' => $user->id, 'news_url_id' => $newsUrl->id, 'type' => 'rapid_user_votes', 'severity' => 'medium']);

        $this->artisan('truthshield:ensure-algorithm-version')->assertExitCode(0);
        $this->assertSame(1, AlgorithmVersion::query()->count());

        $this->artisan('truthshield:detect-abuse-clusters')->assertExitCode(0);
        $this->assertSame(1, AbuseCluster::query()->count());
    }

    public function test_notifications_can_be_listed_and_marked_read(): void
    {
        $user = User::factory()->create();
        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'test',
            'title' => '測試通知',
            'body' => '通知內容',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.title', '測試通知');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/me/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertSame(0, UserNotification::query()->whereNull('read_at')->count());
    }

    public function test_domain_report_rolls_up_pending_reports_for_same_domain(): void
    {
        $payload = [
            'url' => 'https://rollup-news.test/story/1',
            'page_title' => 'Rollup News',
        ];

        $this->postJson('/api/news-domain-reports', $payload)->assertCreated();
        $this->postJson('/api/news-domain-reports', $payload)->assertCreated();

        $this->assertSame(1, NewsDomainReport::query()->where('domain', 'rollup-news.test')->count());
        $this->assertSame(2, NewsDomainReport::query()->where('domain', 'rollup-news.test')->value('report_count'));

        $this->getJson('/api/news-domain-reports/status?domain=rollup-news.test')
            ->assertOk()
            ->assertJsonPath('is_reported', true)
            ->assertJsonPath('report.report_count', 2);
    }

    public function test_community_url_classification_and_trusted_source_suggestions_roll_up(): void
    {
        $trustedUser = User::factory()->create(['trust_score' => 2.4]);
        $token = $trustedUser->createToken('community-maintenance')->plainTextToken;
        $articlePayload = [
            'url' => 'https://community-news.test/news/politics/202605070001',
            'classification' => 'article',
            'page_title' => '社群分類測試',
            'note' => '這是單篇新聞。',
        ];

        $this->postJson('/api/url-classification-reports', $articlePayload)
            ->assertCreated()
            ->assertJsonPath('report.domain', 'community-news.test')
            ->assertJsonPath('report.classification', 'article')
            ->assertJsonPath('report.report_count', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/url-classification-reports', $articlePayload)
            ->assertCreated();
        $this->assertSame(1, UrlClassificationReport::query()->where('domain', 'community-news.test')->count());
        $this->assertSame(2, UrlClassificationReport::query()->where('domain', 'community-news.test')->value('report_count'));
        $this->assertSame(2.65, round((float) UrlClassificationReport::query()->where('domain', 'community-news.test')->value('weighted_score'), 2));
        $this->assertNotNull(UrlClassificationReport::query()->where('domain', 'community-news.test')->value('suggested_pattern'));

        $this->postJson('/api/trusted-source-suggestions', [
            'url' => 'https://drive.google.com/file/d/example/view',
            'source_type' => 'cloud_drive',
            'note' => '常用雲端證據來源。',
        ])
            ->assertCreated()
            ->assertJsonPath('suggestion.host', 'drive.google.com')
            ->assertJsonPath('suggestion.source_type', 'cloud_drive');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/trusted-source-suggestions', [
                'host' => 'drive.google.com',
                'source_type' => 'cloud_drive',
            ])->assertCreated();

        $this->assertSame(1, TrustedSourceSuggestion::query()->where('host', 'drive.google.com')->count());
        $this->assertSame(2, TrustedSourceSuggestion::query()->where('host', 'drive.google.com')->value('report_count'));
        $this->assertSame(4.8, round((float) TrustedSourceSuggestion::query()->where('host', 'drive.google.com')->value('weighted_score'), 2));
    }

    public function test_community_signals_are_deduplicated_and_anonymous_reports_do_not_auto_approve(): void
    {
        config([
            'truthshield_community.min_distinct_users' => 2,
            'truthshield_community.thresholds.domain_report' => 2.0,
        ]);

        $user = User::factory()->create(['trust_score' => 3]);
        $token = $user->createToken('community')->plainTextToken;
        $payload = ['url' => 'https://self-managed-news.test/story/1'];

        $this->postJson('/api/news-domain-reports', $payload)->assertCreated();
        $this->postJson('/api/news-domain-reports', $payload)->assertCreated();

        $this->withToken($token)->postJson('/api/news-domain-reports', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/news-domain-reports', $payload)->assertCreated();

        $this->assertSame(4, NewsDomainReport::query()->where('domain', 'self-managed-news.test')->value('report_count'));
        $this->assertSame(3, CommunitySignal::query()->where('signal_type', 'domain_report')->where('subject_key', 'self-managed-news.test')->count());

        $this->artisan('truthshield:run-community-automation')->assertExitCode(0);
        $this->assertDatabaseMissing('news_domains', ['domain' => 'self-managed-news.test']);
        $this->assertDatabaseHas('community_tasks', [
            'type' => 'domain_candidate',
            'subject_key' => 'self-managed-news.test',
            'status' => 'open',
        ]);

        $task = CommunityTask::query()->where('subject_key', 'self-managed-news.test')->firstOrFail();
        $this->getJson("/api/community/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('gap.remaining_users', 1)
            ->assertJsonStructure(['task', 'summary', 'gap', 'actions']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/community/tasks/{$task->id}/signal", [
                'value' => 'confirm_news_domain',
                'note' => '這是新聞站。',
            ])
            ->assertCreated()
            ->assertJsonPath('signal.subject_key', 'self-managed-news.test');

        $this->get('/api/exports/community-signals.csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');
    }

    public function test_community_automation_auto_approves_low_risk_domain_url_rule_and_trusted_source(): void
    {
        config([
            'truthshield_community.min_distinct_users' => 2,
            'truthshield_community.thresholds.domain_report' => 2.0,
            'truthshield_community.thresholds.url_classification' => 2.0,
            'truthshield_community.thresholds.trusted_source' => 2.0,
        ]);

        $users = User::factory()->count(2)->create(['trust_score' => 1.5]);
        $domainPayload = ['url' => 'https://auto-news.test/news/202605070001', 'page_title' => 'Auto News'];
        $rulePayload = ['url' => 'https://auto-news.test/news/202605070001', 'classification' => 'article'];
        $sourcePayload = ['host' => 'drive.google.com', 'source_type' => 'cloud_drive'];

        foreach ($users as $user) {
            $token = $user->createToken('community')->plainTextToken;
            $this->withToken($token)->postJson('/api/news-domain-reports', $domainPayload)->assertCreated();
            $this->withToken($token)->postJson('/api/url-classification-reports', $rulePayload)->assertCreated();
            $this->withToken($token)->postJson('/api/trusted-source-suggestions', $sourcePayload)->assertCreated();
        }

        $this->artisan('truthshield:run-community-automation')->assertExitCode(0);

        $this->assertDatabaseHas('news_domains', [
            'domain' => 'auto-news.test',
            'is_active' => true,
        ]);
        $this->assertNotNull(NewsDomain::query()->where('domain', 'auto-news.test')->value('article_url_pattern'));
        $this->assertDatabaseHas('trusted_evidence_sources', [
            'host' => 'drive.google.com',
            'source_type' => 'cloud_drive',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('news_domain_reports', ['domain' => 'auto-news.test', 'status' => 'community_approved']);
        $this->assertDatabaseHas('url_classification_reports', ['domain' => 'auto-news.test', 'status' => 'community_approved']);
        $this->assertDatabaseHas('trusted_source_suggestions', ['host' => 'drive.google.com', 'status' => 'community_approved']);
    }

    public function test_community_policy_can_be_overridden_from_system_settings(): void
    {
        SystemSetting::query()->create([
            'key' => 'community_policy',
            'value' => [
                'min_distinct_users' => 1,
                'thresholds' => ['domain_report' => 1.0],
                'high_risk_domain_keywords' => ['blocked'],
            ],
            'description' => 'Test community policy override.',
        ]);

        $user = User::factory()->create(['trust_score' => 1.2]);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/news-domain-reports', ['url' => 'https://settings-news.test/story/1'])
            ->assertCreated();

        $this->artisan('truthshield:run-community-automation')->assertExitCode(0);
        $this->assertDatabaseHas('news_domains', ['domain' => 'settings-news.test']);
    }

    public function test_high_risk_source_escalates_and_evidence_can_be_soft_demoted(): void
    {
        config([
            'truthshield_community.min_distinct_users' => 2,
            'truthshield_community.thresholds.trusted_source' => 2.0,
            'truthshield_community.thresholds.evidence_unhelpful' => 1.0,
            'truthshield.evidence_reaction_min_trust_score' => 0.5,
        ]);

        $users = User::factory()->count(2)->create(['trust_score' => 1.5]);
        foreach ($users as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/trusted-source-suggestions', ['host' => 'sponsored.example', 'source_type' => 'sponsored'])
                ->assertCreated();
        }

        $this->seed(TagSeeder::class);
        $author = User::factory()->create(['trust_score' => 1.2]);
        $tag = Tag::query()->where('slug', 'out-of-context')->firstOrFail();
        $this->actingAs($author, 'sanctum')
            ->postJson('/api/vote', [
                'url' => 'https://www.cna.com.tw/news/aipl/202605070002.aspx',
                'tag_id' => $tag->id,
                'evidence_url' => 'https://example.com/weak-evidence',
                'evidence_note' => '需要更多來源確認。',
            ])
            ->assertCreated();

        $vote = Vote::query()->firstOrFail();
        foreach ($users as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson("/api/evidence/{$vote->id}/reaction", ['helpful' => false])
                ->assertOk();
        }

        $this->artisan('truthshield:run-community-automation')->assertExitCode(0);

        $this->assertDatabaseMissing('trusted_evidence_sources', ['host' => 'sponsored.example']);
        $this->assertDatabaseHas('community_tasks', [
            'type' => 'trusted_source_candidate',
            'subject_key' => 'sponsored.example|sponsored',
            'status' => 'escalated',
        ]);
        $this->assertSame('community_demoted', $vote->evidence->refresh()->moderation_status);

        $this->getJson('/api/community/tasks?status=escalated')
            ->assertOk()
            ->assertJsonStructure(['meta', 'data' => [['id', 'type', 'priority', 'status', 'metrics']]]);

        $this->getJson('/api/community/tasks/stats')
            ->assertOk()
            ->assertJsonPath('escalated_tasks', 1)
            ->assertJsonPath('community_demoted_evidence', 1);
    }

    public function test_api_clients_can_be_created_listed_and_revoked(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $payload = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/me/api-clients', [
                'name' => 'Research API',
                'abilities' => ['read:exports'],
            ])
            ->assertCreated()
            ->assertJsonPath('client.name', 'Research API')
            ->assertJsonPath('client.status', 'active')
            ->assertJsonStructure(['plain_key'])
            ->json();

        $this->assertStringStartsWith('ts_', $payload['plain_key']);
        $client = ApiClient::query()->firstOrFail();
        $this->assertNotSame($payload['plain_key'], $client->key_hash);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/me/api-clients')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Research API');

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/me/api-clients/{$client->id}/revoke")
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/me/api-clients/{$client->id}/revoke")
            ->assertOk()
            ->assertJsonPath('client.status', 'revoked');
    }

    public function test_vote_and_reaction_write_account_signals_and_graph_command_creates_edges(): void
    {
        $this->seed(TagSeeder::class);
        $author = User::factory()->create();
        $reviewer = User::factory()->create(['trust_score' => 1]);
        $tag = Tag::query()->where('slug', 'out-of-context')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($author, 'sanctum')
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'TruthShieldTest/1.0'])
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://example.com/context-report',
                'evidence_note' => '相關報導提供完整時間線。',
            ])
            ->assertCreated();

        $vote = Vote::query()->firstOrFail();

        $this->actingAs($reviewer, 'sanctum')
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'TruthShieldTest/1.0'])
            ->postJson("/api/evidence/{$vote->id}/reaction", ['helpful' => true])
            ->assertOk();

        $this->assertGreaterThanOrEqual(4, AccountSignal::query()->count());

        $this->artisan('truthshield:build-account-graph')->assertExitCode(0);

        $this->assertDatabaseHas('account_edges', [
            'source_user_id' => min($author->id, $reviewer->id),
            'target_user_id' => max($author->id, $reviewer->id),
            'edge_type' => 'shared_ip',
        ]);

        $this->actingAs($author, 'sanctum')
            ->getJson('/api/account-graph/summary')
            ->assertOk()
            ->assertJsonPath('edges', AccountEdge::query()->count())
            ->assertJsonStructure(['signals_7d', 'edges', 'high_risk_edges', 'top_edges']);
    }

    public function test_health_and_transparency_include_operational_defense_fields(): void
    {
        OperationalEvent::query()->create(['type' => 'queue_worker', 'status' => 'ok']);
        BugReport::query()->create([
            'report_type' => 'security',
            'severity' => 'critical',
            'status' => 'new',
            'title' => 'Token leak',
            'description' => 'Token is visible in logs.',
        ]);
        AccountEdge::query()->create([
            'source_user_id' => User::factory()->create()->id,
            'target_user_id' => User::factory()->create()->id,
            'edge_type' => 'shared_ip',
            'score' => 70,
        ]);

        $this->getJson('/api/system/health')
            ->assertOk()
            ->assertJsonPath('queue.healthy', true)
            ->assertJsonPath('counts.high_risk_account_edges', 1)
            ->assertJsonPath('counts.open_security_reports', 1)
            ->assertJsonPath('counts.critical_bug_reports', 1)
            ->assertJsonPath('thresholds.status_cache_version', 'v1');

        $this->getJson('/api/transparency')
            ->assertOk()
            ->assertJsonPath('high_risk_account_edges', 1)
            ->assertJsonPath('open_security_reports', 1)
            ->assertJsonPath('bug_report_distribution.new', 1)
            ->assertJsonStructure(['active_api_clients', 'operational_events_24h', 'status_cache_version']);
    }

    public function test_oauth_begin_state_and_identity_link_flow(): void
    {
        $state = $this->postJson('/api/auth/google/begin', [
            'redirect_url' => 'https://truthshield.test/auth/callback',
        ])
            ->assertOk()
            ->assertJsonPath('provider', 'google')
            ->assertJsonStructure(['state', 'expires_at', 'auth_url'])
            ->json('state');

        $this->postJson('/api/auth/google/callback', [
            'provider_user_id' => 'google-1',
            'email' => 'google@example.com',
            'name' => 'Google User',
            'state' => $state,
        ])
            ->assertOk()
            ->assertJsonPath('user.identity_level', 'oauth');

        $user = User::query()->where('email', 'google@example.com')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/github/link', [
                'provider_user_id' => 'gh-1',
                'email' => 'google@example.com',
                'display_name' => 'GitHub User',
            ])
            ->assertOk()
            ->assertJsonPath('identity.provider', 'github');

        $this->assertSame(2, UserIdentity::query()->where('user_id', $user->id)->count());

        $this->postJson('/api/auth/google/callback', [
            'provider_user_id' => 'google-2',
            'email' => 'bad-state@example.com',
            'state' => $state,
        ])->assertStatus(422);
    }

    public function test_launch_ops_sources_rate_limits_and_selector_checks(): void
    {
        NewsDomain::query()->create([
            'domain' => 'selectors.test',
            'is_active' => true,
            'article_selector' => 'article',
        ]);

        $this->artisan('truthshield:seed-launch-policies')->assertExitCode(0);
        $this->assertGreaterThanOrEqual(4, RateLimitPolicy::query()->count());
        $this->assertGreaterThanOrEqual(1, TrustedEvidenceSource::query()->count());

        $this->artisan('truthshield:check-extension-selectors')->assertExitCode(0);
        $this->assertGreaterThanOrEqual(1, ExtensionSelectorCheck::query()->where('domain', 'selectors.test')->count());

        $this->postJson('/api/extension/selector-checks', [
            'domain' => 'selectors.test',
            'check_type' => 'article_mount_runtime',
            'success' => false,
            'metadata' => ['mode' => 'fixed_fallback'],
        ])
            ->assertCreated()
            ->assertJsonPath('check.success', false);

        $this->getJson('/api/trusted-evidence-sources')
            ->assertOk()
            ->assertJsonStructure(['data' => [['host', 'source_type', 'trust_bonus']]]);

        $this->getJson('/api/rate-limit-policies')
            ->assertOk()
            ->assertJsonFragment(['name' => 'hover']);

        $payload = $this->getJson('/api/extension/selector-checks')
            ->assertOk()
            ->json();

        $this->assertGreaterThanOrEqual(1, $payload['summary']['failed_24h']);
    }

    public function test_admin_governance_can_hide_restore_review_restrict_and_adjust(): void
    {
        $this->seed(TagSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create(['trust_score' => 1.5]);
        $tag = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://i.imgur.com/admin.png',
                'evidence_note' => '管理測試證據。',
            ])
            ->assertCreated();

        $evidence = Evidence::query()->firstOrFail();
        $vote = Vote::query()->firstOrFail();
        $report = EvidenceReport::query()->create([
            'vote_id' => $vote->id,
            'user_id' => $admin->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
        $abuse = AbuseEvent::query()->create([
            'user_id' => $author->id,
            'news_url_id' => $vote->news_url_id,
            'type' => 'test_abuse',
            'severity' => 'high',
        ]);
        $appeal = Appeal::query()->create([
            'user_id' => $author->id,
            'subject_type' => 'trust',
            'subject_id' => $author->id,
            'reason' => 'incorrect_penalty',
            'statement' => '請重新檢視。',
        ]);

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/admin/evidences/{$evidence->id}/hide", ['reason' => 'not admin'])
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/evidences/{$evidence->id}/hide", ['reason' => '包含不當資訊'])
            ->assertOk()
            ->assertJsonPath('evidence.hidden', true);

        $this->assertDatabaseHas('votes', ['id' => $vote->id, 'hidden' => true]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/evidences/{$evidence->id}/restore", ['reason' => '申訴後恢復'])
            ->assertOk()
            ->assertJsonPath('evidence.hidden', false);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/evidence-reports/{$report->id}/review", [
                'status' => 'resolved',
                'review_note' => '已處理。',
            ])
            ->assertOk()
            ->assertJsonPath('report.status', 'resolved');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/abuse-events/{$abuse->id}/review", [
                'action_taken' => 'limit_user',
                'review_note' => '短時間異常行為。',
            ])
            ->assertOk()
            ->assertJsonPath('event.reviewed', true);

        $this->assertDatabaseHas('users', ['id' => $author->id, 'risk_status' => 'limited']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$author->id}/risk", [
                'risk_status' => 'normal',
                'reason' => '人工解除限制。',
            ])
            ->assertOk()
            ->assertJsonPath('user.risk_status', 'normal');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$author->id}/trust-adjustment", [
                'delta' => 0.2,
                'reason' => 'manual_review',
                'details' => '補償誤判。',
            ])
            ->assertOk()
            ->assertJsonPath('user.trust_score', 1.7);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/appeals/{$appeal->id}/review", [
                'status' => 'approved',
                'review_note' => '申訴成立。',
            ])
            ->assertOk()
            ->assertJsonPath('appeal.status', 'approved');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/trusted-evidence-sources', [
                'host' => 'trusted.example.com',
                'source_type' => 'fact_check',
                'trust_bonus' => 12,
            ])
            ->assertCreated()
            ->assertJsonPath('source.host', 'trusted.example.com');

        $this->assertDatabaseHas('moderation_events', ['event_type' => 'appeal.reviewed']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $author->id, 'type' => 'appeal.reviewed']);
    }

    public function test_socialite_begin_url_selector_fixture_import_and_status_stress_command(): void
    {
        $authUrl = $this->postJson('/api/auth/github/begin')
            ->assertOk()
            ->json('auth_url');

        $this->assertStringContainsString('/api/auth/github/redirect', $authUrl);

        $this->artisan('truthshield:import-selector-fixtures')->assertExitCode(0);
        $this->assertGreaterThanOrEqual(30, NewsDomain::query()->count());

        $this->artisan('truthshield:stress-status --requests=5')->assertExitCode(0);
    }

    public function test_snapshot_job_fetches_metadata_and_finalize_job_settles_news(): void
    {
        Http::fake([
            'i.imgur.com/*' => Http::response('', 200, [
                'content-type' => 'image/png',
                'content-length' => '1234',
            ]),
        ]);

        $this->seed(TagSeeder::class);
        $user = User::factory()->create(['trust_score' => 2]);
        $tag = Tag::query()->where('slug', 'clickbait-title')->firstOrFail();
        $url = 'https://www.cna.com.tw/news/aipl/202605060001.aspx';

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vote', [
                'url' => $url,
                'tag_id' => $tag->id,
                'evidence_url' => 'https://i.imgur.com/snapshot.png',
                'evidence_note' => '真實 metadata 抓取測試。',
            ])
            ->assertCreated();

        $evidence = Evidence::query()->firstOrFail();
        SnapshotEvidenceJob::dispatchSync($evidence->id);

        $this->assertDatabaseHas('evidence_snapshots', [
            'evidence_id' => $evidence->id,
            'status' => 'snapshotted',
        ]);
        $this->assertSame('snapshotted', $evidence->refresh()->snapshot_status);
        $this->assertSame('image/png', data_get($evidence->metadata, 'last_snapshot.content_type'));

        $newsUrl = NewsUrl::query()->firstOrFail();
        $newsUrl->forceFill(['voting_closes_at' => now()->subMinute()])->save();
        FinalizeNewsUrlJob::dispatchSync($newsUrl->id, true);

        $this->assertNotNull($newsUrl->refresh()->finalized_at);
        $this->assertSame(1, TrustSettlement::query()->count());
    }

    public function test_snapshot_job_rejects_unsafe_redirect_and_disallowed_content_type(): void
    {
        Http::fake([
            'safe.example.com/private-redirect' => Http::response('', 302, ['location' => 'http://127.0.0.1/private']),
            'safe.example.com/binary' => Http::response('', 200, ['content-type' => 'application/octet-stream']),
        ]);

        $newsUrl = NewsUrl::query()->create([
            'hash' => 'snapshot-hardening',
            'original_url' => 'https://news.example.com/story',
            'normalized_url' => 'https://news.example.com/story',
            'voting_closes_at' => now()->addHours(72),
        ]);
        $user = User::factory()->create();
        $tag = Tag::query()->create(['name' => '測試標籤', 'slug' => 'test-tag', 'color' => '#fff', 'severity' => 'medium']);
        $vote = Vote::query()->create([
            'user_id' => $user->id,
            'news_url_id' => $newsUrl->id,
            'tag_id' => $tag->id,
            'evidence_url' => 'https://safe.example.com/binary',
            'evidence_type' => 'link',
            'evidence_host' => 'safe.example.com',
            'evidence_safety' => 'unverified',
            'weight_score' => 1,
        ]);

        $evidence = Evidence::query()->create([
            'vote_id' => $vote->id,
            'news_url_id' => $newsUrl->id,
            'user_id' => $user->id,
            'url' => 'https://safe.example.com/binary',
            'host' => 'safe.example.com',
            'type' => 'link',
            'safety' => 'unverified',
        ]);

        SnapshotEvidenceJob::dispatchSync($evidence->id);

        $this->assertSame('failed', $evidence->refresh()->snapshot_status);
        $this->assertStringContainsString('content type', data_get($evidence->metadata, 'last_snapshot.error'));
    }

    public function test_ecpay_donation_checkout_payload_and_callback(): void
    {
        config([
            'services.ecpay.checkout_url' => 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5',
            'services.ecpay.api_base_url' => 'http://127.0.0.1:18080',
            'services.ecpay.web_base_url' => 'http://127.0.0.1:15173',
        ]);

        $checkout = $this->postJson('/api/donations/ecpay', [
            'amount' => 300,
            'donor_name' => '測試捐款者',
            'donor_email' => 'donor@example.com',
            'message' => '支持真相護盾。',
        ])
            ->assertCreated()
            ->assertJsonPath('donation.amount', 300)
            ->assertJsonPath('checkout.method', 'POST')
            ->assertJsonPath('checkout.url', 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5')
            ->assertJsonStructure([
                'checkout' => [
                    'params' => [
                        'MerchantID',
                        'MerchantTradeNo',
                        'MerchantTradeDate',
                        'PaymentType',
                        'TotalAmount',
                        'TradeDesc',
                        'ItemName',
                        'ReturnURL',
                        'ChoosePayment',
                        'ClientBackURL',
                        'EncryptType',
                        'CheckMacValue',
                    ],
                ],
            ])
            ->json('checkout.params');

        $tradeNo = $checkout['MerchantTradeNo'];
        $donation = Donation::query()->where('merchant_trade_no', $tradeNo)->firstOrFail();

        $this->assertSame('pending', $donation->status);
        $this->assertSame($checkout['CheckMacValue'], data_get($donation->request_payload, 'CheckMacValue'));

        $callback = [
            'MerchantID' => $checkout['MerchantID'],
            'MerchantTradeNo' => $tradeNo,
            'RtnCode' => '1',
            'RtnMsg' => 'Succeeded',
            'TradeNo' => '1234567890',
            'TradeAmt' => '300',
            'PaymentDate' => now()->format('Y/m/d H:i:s'),
            'PaymentType' => 'Credit_CreditCard',
            'PaymentTypeChargeFee' => '10',
            'TradeDate' => now()->format('Y/m/d H:i:s'),
            'SimulatePaid' => '1',
        ];
        $callback['CheckMacValue'] = app(\App\Services\EcpayDonationService::class)->checkMacValue($callback);

        $this->post('/api/donations/ecpay/notify', $callback)
            ->assertOk()
            ->assertSee('1|OK');

        $this->assertDatabaseHas('donations', [
            'merchant_trade_no' => $tradeNo,
            'status' => 'paid',
        ]);

        $this->getJson('/api/donations/' . $tradeNo)
            ->assertOk()
            ->assertJsonPath('donation.status', 'paid');
    }

    public function test_ecpay_donation_rejects_invalid_callback_signature(): void
    {
        $checkout = $this->postJson('/api/donations/ecpay', ['amount' => 100])
            ->assertCreated()
            ->json('checkout.params');

        $this->post('/api/donations/ecpay/notify', [
            'MerchantID' => $checkout['MerchantID'],
            'MerchantTradeNo' => $checkout['MerchantTradeNo'],
            'RtnCode' => '1',
            'CheckMacValue' => 'invalid',
        ])->assertStatus(400);

        $this->assertDatabaseHas('donations', [
            'merchant_trade_no' => $checkout['MerchantTradeNo'],
            'status' => 'pending',
        ]);
    }

    public function test_donation_config_and_amount_validation(): void
    {
        config(['truthshield.donation_amounts' => [120, 360]]);

        $this->getJson('/api/donations/config')
            ->assertOk()
            ->assertJsonPath('amounts.0', 120)
            ->assertJsonPath('amounts.1', 360)
            ->assertJsonPath('provider', 'ecpay');

        $this->postJson('/api/donations/ecpay', ['amount' => 300])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->postJson('/api/donations/ecpay', ['amount' => 360])
            ->assertCreated();
    }

    public function test_user_data_request_can_be_submitted(): void
    {
        $this->postJson('/api/user-data-requests', [
            'email' => 'privacy@example.com',
            'request_type' => 'deletion',
            'reason' => '請協助刪除帳號相關資料。',
        ])
            ->assertCreated()
            ->assertJsonPath('request.email', 'privacy@example.com')
            ->assertJsonPath('request.status', 'pending');

        $this->assertDatabaseHas('user_data_requests', [
            'email' => 'privacy@example.com',
            'request_type' => 'deletion',
            'status' => 'pending',
        ]);
    }

    public function test_bug_and_security_report_can_be_submitted(): void
    {
        $this->postJson('/api/bug-reports', [
            'report_type' => 'security',
            'title' => 'postMessage origin validation issue',
            'description' => 'The iframe accepts messages from an unexpected origin.',
            'steps_to_reproduce' => 'Open a crafted page and send a resize message.',
            'page_url' => 'https://example.com/news/1',
            'contact_email' => 'security@example.com',
            'extension_version' => '0.1.0',
            'source' => 'extension_popup',
            'diagnostics' => ['browser' => 'Chrome'],
        ])
            ->assertCreated()
            ->assertJsonPath('report.report_type', 'security')
            ->assertJsonPath('report.severity', 'high')
            ->assertJsonPath('report.status', 'new');

        $this->assertDatabaseHas('bug_reports', [
            'report_type' => 'security',
            'title' => 'postMessage origin validation issue',
            'status' => 'new',
            'source' => 'extension_popup',
        ]);
    }

    public function test_vision_readiness_endpoint_returns_55_local_feature_points(): void
    {
        $this->seed();

        $this->getJson('/api/vision-readiness')
            ->assertOk()
            ->assertJsonPath('summary.local_feature_points', 55)
            ->assertJsonPath('summary.completed_local_points', 55)
            ->assertJsonPath('summary.local_next_points', 0)
            ->assertJsonPath('summary.local_completed_polish_points', 22)
            ->assertJsonCount(55, 'feature_points')
            ->assertJsonCount(0, 'local_next_points')
            ->assertJsonCount(22, 'local_completed_polish_points')
            ->assertJsonStructure([
                'categories',
                'feature_points' => [['id', 'category', 'title', 'status']],
                'local_next_points',
                'local_completed_polish_points' => [['id', 'category', 'title', 'impact']],
                'journalism_taxonomy' => ['negative', 'positive'],
                'evidence_rubric',
                'participation_loops',
                'operational_playbooks',
                'production_checklist',
                'security_report_flow',
                'live_pressure',
                'community_self_management',
                'launch_dependencies',
            ]);
    }
}
