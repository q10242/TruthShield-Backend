<?php

namespace Tests\Feature;

use App\Filament\Resources\NewsEventResource;
use App\Models\EventEditLog;
use App\Models\ModerationEvent;
use App\Models\NewsEvent;
use App\Models\NewsUrl;
use App\Models\User;
use App\Services\UrlFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_options_endpoint_returns_fixed_taxonomy(): void
    {
        $this->getJson('/api/events/options')
            ->assertOk()
            ->assertJsonPath('primary_categories.0.value', 'social_case')
            ->assertJsonPath('tags.0.value', 'children')
            ->assertJsonPath('progress_statuses.0.value', 'collecting');
    }

    public function test_user_can_create_event_with_category_tags_and_progress_status(): void
    {
        $user = User::factory()->create(['trust_score' => 2.0]);

        $event = $this->withHeader('Accept-Language', 'zh-TW')
            ->actingAs($user, 'sanctum')
            ->postJson('/api/events', [
                'name' => '愷愷案時間線',
                'summary' => '整理事件前因後果與修法結果。',
                'news_url' => 'https://news.example.test/kai-kai',
                'primary_category' => 'social_case',
                'tags' => ['children', 'law_reform'],
                'progress_status' => 'tracking',
            ])
            ->assertCreated()
            ->assertJsonPath('data.primary_category', 'social_case')
            ->assertJsonPath('data.primary_category_label', '社會案件')
            ->assertJsonPath('data.tags.0', 'children')
            ->assertJsonPath('data.tag_labels.1', '修法')
            ->assertJsonPath('data.progress_status', 'tracking')
            ->assertJsonPath('data.progress_status_label', '持續追蹤')
            ->json('data');

        $this->getJson('/api/events?primary_category=social_case&tag=law_reform&progress_status=tracking')
            ->assertOk()
            ->assertJsonPath('data.0.id', $event['id']);

        $this->getJson('/api/events?primary_category=politics&tag=law_reform&progress_status=tracking')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_invalid_event_taxonomy_is_rejected(): void
    {
        $user = User::factory()->create(['trust_score' => 2.0]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', [
                'name' => '錯誤分類事件',
                'news_url' => 'https://news.example.test/invalid-taxonomy',
                'primary_category' => 'wrong',
                'tags' => ['children', 'wrong'],
                'progress_status' => 'done',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_category', 'tags.1', 'progress_status']);
    }

    public function test_event_routes_resolve_by_slug_for_public_sharing(): void
    {
        $event = NewsEvent::query()->create([
            'name' => '事件分享測試',
            'slug' => 'event-share-slug',
            'summary' => '測試事件 slug 是否能提供公開 API 與社群預覽。',
            'primary_category' => 'public_policy',
            'progress_status' => 'tracking',
            'last_activity_at' => now(),
        ]);

        $this->getJson('/api/events/event-share-slug')
            ->assertOk()
            ->assertJsonPath('data.id', $event->id);

        $this->get('/share/events/event-share-slug')
            ->assertOk()
            ->assertSee('事件分享測試 | TruthShield 真相護盾', false)
            ->assertSee('/events/'.$event->id, false);
    }

    public function test_high_trust_user_can_update_event_taxonomy_and_logs_change(): void
    {
        $user = User::factory()->create(['trust_score' => 2.0]);
        $event = NewsEvent::query()->create([
            'name' => '事件分類更新',
            'slug' => 'event-taxonomy-update',
            'summary' => '原始事件。',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/events/{$event->id}", [
                'primary_category' => 'public_policy',
                'tags' => ['education', 'law_reform', 'education'],
                'progress_status' => 'resolved',
            ])
            ->assertOk()
            ->assertJsonPath('data.primary_category', 'public_policy')
            ->assertJsonPath('data.tags.0', 'education')
            ->assertJsonPath('data.tags.1', 'law_reform')
            ->assertJsonPath('data.progress_status', 'resolved');

        $this->assertSame(['education', 'law_reform'], $event->refresh()->tags);
        $this->assertDatabaseHas((new EventEditLog)->getTable(), [
            'news_event_id' => $event->id,
            'action' => 'updated',
            'subject_type' => 'NewsEvent',
        ]);
        $this->assertDatabaseHas((new ModerationEvent)->getTable(), [
            'event_type' => 'event.metadata_updated',
            'subject_id' => $event->id,
        ]);
    }

    public function test_admin_event_resource_records_public_governance_logs(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $event = NewsEvent::query()->create([
            'name' => '後台治理紀錄',
            'slug' => 'admin-governance-log',
            'summary' => '後台更新前。',
            'primary_category' => 'public_policy',
            'tags' => ['law_reform'],
            'progress_status' => 'tracking',
            'status' => 'active',
            'last_activity_at' => now(),
        ]);

        $before = $event->toArray();
        $this->actingAs($admin);

        $event->forceFill([
            'tags' => ['law_reform', 'platform_governance'],
            'progress_status' => 'disputed',
            'is_disputed' => true,
        ])->save();

        NewsEventResource::recordAdminEventGovernance(
            $event->fresh(),
            $before,
            'updated',
            'event.admin_marked_disputed',
            "管理台標記事件「{$event->name}」為需先求證。",
            'Admin marked event as disputed while keeping it publicly visible.',
        );

        $this->assertDatabaseHas((new EventEditLog)->getTable(), [
            'news_event_id' => $event->id,
            'user_id' => $admin->id,
            'action' => 'updated',
            'subject_type' => 'NewsEvent',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas((new ModerationEvent)->getTable(), [
            'user_id' => $admin->id,
            'event_type' => 'event.admin_marked_disputed',
            'subject_id' => $event->id,
        ]);
    }

    public function test_low_trust_user_cannot_update_event_taxonomy(): void
    {
        config(['truthshield.event_system_min_trust_score' => 1.0]);

        $lowTrust = User::factory()->create(['trust_score' => 0.2]);
        $event = NewsEvent::query()->create([
            'name' => '低信用不可改',
            'slug' => 'low-trust-cannot-edit-taxonomy',
            'summary' => '保留原分類。',
            'primary_category' => 'social_case',
            'progress_status' => 'collecting',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($lowTrust, 'sanctum')
            ->patchJson("/api/events/{$event->id}", [
                'primary_category' => 'politics',
                'progress_status' => 'resolved',
            ])
            ->assertForbidden();

        $event->refresh();
        $this->assertSame('social_case', $event->primary_category);
        $this->assertSame('collecting', $event->progress_status);
    }

    public function test_reader_reaction_summary_exposes_related_event_taxonomy(): void
    {
        $fingerprint = app(UrlFingerprintService::class)->fingerprint('https://news.example.test/related-event-taxonomy');
        $newsUrl = NewsUrl::query()->create([
            'hash' => $fingerprint['hash'],
            'original_url' => $fingerprint['original_url'],
            'normalized_url' => $fingerprint['normalized_url'],
            'voting_closes_at' => now()->addHours(72),
        ]);
        $event = NewsEvent::query()->create([
            'name' => '相關事件分類',
            'slug' => 'related-event-taxonomy',
            'summary' => '用於 extension 顯示分類與進度。',
            'primary_news_url_id' => $newsUrl->id,
            'primary_category' => 'social_case',
            'tags' => ['children', 'law_reform'],
            'progress_status' => 'tracking',
            'last_activity_at' => now(),
        ]);

        $this->withHeader('Accept-Language', 'zh-TW')
            ->getJson('/api/reactions/summary?news_url='.urlencode('https://news.example.test/related-event-taxonomy'))
            ->assertOk()
            ->assertJsonPath('related_events.0.id', $event->id)
            ->assertJsonPath('related_events.0.primary_category_label', '社會案件')
            ->assertJsonPath('related_events.0.progress_status_label', '持續追蹤')
            ->assertJsonPath('related_events.0.tag_labels.1', '修法');
    }
}
