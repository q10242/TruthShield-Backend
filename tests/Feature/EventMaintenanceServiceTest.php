<?php

namespace Tests\Feature;

use App\Models\EventEditLog;
use App\Models\ModerationEvent;
use App\Models\NewsEvent;
use App\Models\NewsEventItem;
use App\Models\NewsEventTimelineEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventMaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_vasp_stablecoin_regulation_task_dry_run_does_not_write_data(): void
    {
        User::factory()->create(['is_admin' => true, 'trust_score' => 1.5]);

        $this->artisan('truthshield:maintain-events vasp_stablecoin_regulation_event')
            ->assertSuccessful();

        $this->assertDatabaseMissing((new NewsEvent)->getTable(), [
            'slug' => 'vasp-stablecoin-regulation-2026',
        ]);
    }

    public function test_vasp_stablecoin_regulation_task_creates_event_sources_timeline_and_governance_logs(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'trust_score' => 1.5]);

        $this->artisan('truthshield:maintain-events vasp_stablecoin_regulation_event --execute')
            ->assertSuccessful();

        $event = NewsEvent::query()->where('slug', 'vasp-stablecoin-regulation-2026')->firstOrFail();

        $this->assertSame('虛擬資產服務法草案與穩定幣監理', $event->name);
        $this->assertSame('finance', $event->primary_category);
        $this->assertSame(['law_reform', 'platform_governance', 'fraud'], $event->tags);
        $this->assertSame('tracking', $event->progress_status);
        $this->assertSame($admin->id, $event->created_by);
        $this->assertSame(3, NewsEventItem::query()->where('news_event_id', $event->id)->count());
        $this->assertSame(3, NewsEventTimelineEntry::query()->where('news_event_id', $event->id)->count());

        $this->assertDatabaseHas((new EventEditLog)->getTable(), [
            'news_event_id' => $event->id,
            'action' => 'created',
            'subject_type' => 'NewsEvent',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas((new ModerationEvent)->getTable(), [
            'user_id' => $admin->id,
            'event_type' => 'event.timeline_maintained',
            'subject_id' => $event->id,
        ]);

        $this->artisan('truthshield:maintain-events vasp_stablecoin_regulation_event --execute')
            ->assertSuccessful();

        $this->assertSame(1, NewsEvent::query()->where('slug', 'vasp-stablecoin-regulation-2026')->count());
        $this->assertSame(3, NewsEventItem::query()->where('news_event_id', $event->id)->count());
        $this->assertSame(3, NewsEventTimelineEntry::query()->where('news_event_id', $event->id)->count());
    }

    public function test_east_coast_maritime_enforcement_task_dry_run_does_not_write_data(): void
    {
        User::factory()->create(['is_admin' => true, 'trust_score' => 1.5]);

        $this->artisan('truthshield:maintain-events east_coast_maritime_enforcement_event')
            ->assertSuccessful();

        $this->assertDatabaseMissing((new NewsEvent)->getTable(), [
            'slug' => 'east-coast-maritime-enforcement-2026',
        ]);
    }

    public function test_east_coast_maritime_enforcement_task_creates_event_sources_timeline_and_governance_logs(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'trust_score' => 1.5]);

        $this->artisan('truthshield:maintain-events east_coast_maritime_enforcement_event --execute')
            ->assertSuccessful();

        $event = NewsEvent::query()->where('slug', 'east-coast-maritime-enforcement-2026')->firstOrFail();

        $this->assertSame('中國宣稱台灣東部海域執法與海巡應處', $event->name);
        $this->assertSame('international', $event->primary_category);
        $this->assertSame(['national_security', 'cross_strait', 'media_ethics'], $event->tags);
        $this->assertSame('tracking', $event->progress_status);
        $this->assertSame($admin->id, $event->created_by);
        $this->assertSame(4, NewsEventItem::query()->where('news_event_id', $event->id)->count());
        $this->assertSame(4, NewsEventTimelineEntry::query()->where('news_event_id', $event->id)->count());

        $this->assertDatabaseHas((new EventEditLog)->getTable(), [
            'news_event_id' => $event->id,
            'action' => 'created',
            'subject_type' => 'NewsEvent',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas((new ModerationEvent)->getTable(), [
            'user_id' => $admin->id,
            'event_type' => 'event.timeline_maintained',
            'subject_id' => $event->id,
        ]);

        $this->artisan('truthshield:maintain-events east_coast_maritime_enforcement_event --execute')
            ->assertSuccessful();

        $this->assertSame(1, NewsEvent::query()->where('slug', 'east-coast-maritime-enforcement-2026')->count());
        $this->assertSame(4, NewsEventItem::query()->where('news_event_id', $event->id)->count());
        $this->assertSame(4, NewsEventTimelineEntry::query()->where('news_event_id', $event->id)->count());
    }

    public function test_resource_circulation_law_task_dry_run_does_not_write_data(): void
    {
        User::factory()->create(['is_admin' => true, 'trust_score' => 1.5]);

        $this->artisan('truthshield:maintain-events resource_circulation_law_event')
            ->assertSuccessful();

        $this->assertDatabaseMissing((new NewsEvent)->getTable(), [
            'slug' => 'resource-circulation-law-2026',
        ]);
    }

    public function test_resource_circulation_law_task_creates_event_sources_timeline_and_governance_logs(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'trust_score' => 1.5]);

        $this->artisan('truthshield:maintain-events resource_circulation_law_event --execute')
            ->assertSuccessful();

        $event = NewsEvent::query()->where('slug', 'resource-circulation-law-2026')->firstOrFail();

        $this->assertSame('資源循環推動法三讀與循環治理制度建置', $event->name);
        $this->assertSame('public_policy', $event->primary_category);
        $this->assertSame(['environment', 'law_reform'], $event->tags);
        $this->assertSame('tracking', $event->progress_status);
        $this->assertSame($admin->id, $event->created_by);
        $this->assertSame(4, NewsEventItem::query()->where('news_event_id', $event->id)->count());
        $this->assertSame(4, NewsEventTimelineEntry::query()->where('news_event_id', $event->id)->count());

        $this->assertDatabaseHas((new EventEditLog)->getTable(), [
            'news_event_id' => $event->id,
            'action' => 'created',
            'subject_type' => 'NewsEvent',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas((new ModerationEvent)->getTable(), [
            'user_id' => $admin->id,
            'event_type' => 'event.timeline_maintained',
            'subject_id' => $event->id,
        ]);

        $this->artisan('truthshield:maintain-events resource_circulation_law_event --execute')
            ->assertSuccessful();

        $this->assertSame(1, NewsEvent::query()->where('slug', 'resource-circulation-law-2026')->count());
        $this->assertSame(4, NewsEventItem::query()->where('news_event_id', $event->id)->count());
        $this->assertSame(4, NewsEventTimelineEntry::query()->where('news_event_id', $event->id)->count());
    }

    public function test_ops_20260614_task_updates_existing_events_and_creates_growth_events_with_governance_logs(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'trust_score' => 1.5]);
        foreach ([1, 2, 3] as $id) {
            NewsEvent::query()->create([
                'id' => $id,
                'created_by' => $admin->id,
                'name' => "測試事件 {$id}",
                'slug' => "test-event-{$id}",
                'summary' => 'Demo 事件資料，僅供測試。',
                'primary_category' => 'other',
                'tags' => [],
                'progress_status' => 'collecting',
                'status' => 'active',
                'last_activity_at' => now()->subDay(),
            ]);
        }
        $duplicateToxicDriving = NewsEvent::query()->create([
            'id' => 14,
            'created_by' => $admin->id,
            'name' => '依托咪酯電子煙與毒駕管制爭議',
            'slug' => 'old-etomidate-slug',
            'summary' => '既有事件摘要。',
            'primary_category' => 'other',
            'tags' => [],
            'progress_status' => 'collecting',
            'status' => 'active',
            'last_activity_at' => now()->subDay(),
        ]);

        $this->artisan('truthshield:maintain-events ops_20260614_event_copy_and_growth')
            ->assertSuccessful();

        $this->assertDatabaseMissing((new NewsEvent)->getTable(), [
            'slug' => 'foreign-student-hospitality-internship-rights-2026',
        ]);

        $canonicalToxicDriving = NewsEvent::query()->create([
            'created_by' => $admin->id,
            'name' => '依托咪酯電子煙與毒駕管制爭議',
            'slug' => 'etomidate-drugged-driving-law-reform-2026',
            'summary' => '既有主要事件摘要。',
            'primary_category' => 'other',
            'tags' => [],
            'progress_status' => 'tracking',
            'status' => 'active',
            'last_activity_at' => now()->subDay(),
        ]);

        $this->artisan('truthshield:maintain-events ops_20260614_event_copy_and_growth --execute')
            ->assertSuccessful();

        $this->assertStringNotContainsString('Demo', NewsEvent::query()->findOrFail(2)->summary);
        $this->assertSame('public_policy', NewsEvent::query()->findOrFail(1)->primary_category);
        $this->assertSame(['environment', 'rescue'], NewsEvent::query()->findOrFail(3)->tags);

        $foreignStudent = NewsEvent::query()
            ->where('slug', 'foreign-student-hospitality-internship-rights-2026')
            ->firstOrFail();
        $toxicDriving = NewsEvent::query()
            ->where('slug', 'etomidate-drugged-driving-law-reform-2026')
            ->firstOrFail();

        $this->assertSame('public_policy', $foreignStudent->primary_category);
        $this->assertSame(['labor', 'education', 'law_reform'], $foreignStudent->tags);
        $this->assertSame(3, NewsEventItem::query()->where('news_event_id', $foreignStudent->id)->count());
        $this->assertSame(3, NewsEventTimelineEntry::query()->where('news_event_id', $foreignStudent->id)->count());
        $this->assertSame(['traffic', 'law_reform'], $toxicDriving->tags);
        $this->assertSame($canonicalToxicDriving->id, $toxicDriving->id);
        $this->assertSame(3, NewsEventItem::query()->where('news_event_id', $toxicDriving->id)->count());
        $this->assertSame('archived', $duplicateToxicDriving->fresh()->progress_status);

        foreach ([1, 2, 3, $foreignStudent->id, $toxicDriving->id] as $eventId) {
            $this->assertDatabaseHas((new EventEditLog)->getTable(), [
                'news_event_id' => $eventId,
                'is_public' => true,
            ]);
            $this->assertDatabaseHas((new ModerationEvent)->getTable(), [
                'user_id' => $admin->id,
                'subject_id' => $eventId,
            ]);
        }

        $this->artisan('truthshield:maintain-events ops_20260614_event_copy_and_growth --execute')
            ->assertSuccessful();

        $this->assertSame(1, NewsEvent::query()->where('slug', 'foreign-student-hospitality-internship-rights-2026')->count());
        $this->assertSame(2, NewsEvent::query()->where('name', '依托咪酯電子煙與毒駕管制爭議')->count());
        $this->assertSame(1, NewsEvent::query()->where('name', '依托咪酯電子煙與毒駕管制爭議')->where('progress_status', 'tracking')->count());
        $this->assertSame(3, NewsEventItem::query()->where('news_event_id', $foreignStudent->id)->count());
        $this->assertSame(3, NewsEventTimelineEntry::query()->where('news_event_id', $foreignStudent->id)->count());
    }
}
