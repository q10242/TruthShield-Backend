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
}
