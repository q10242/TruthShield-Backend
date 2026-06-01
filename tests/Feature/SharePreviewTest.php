<?php

namespace Tests\Feature;

use App\Models\NewsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_share_preview_includes_event_open_graph_tags(): void
    {
        config([
            'app.url' => 'https://truth-shield-api.otus.tw',
            'app.frontend_url' => 'https://truth-shield.otus.tw',
        ]);

        $event = NewsEvent::query()->create([
            'name' => '愷愷案時間線',
            'slug' => 'kai-kai-case',
            'summary' => '整理愷愷案的前因、新聞追蹤與後續修法結果。',
            'last_activity_at' => now(),
        ]);

        $this->get("/share/events/{$event->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('愷愷案時間線 | TruthShield 真相護盾', false)
            ->assertSee('property="og:title"', false)
            ->assertSee("https://truth-shield.otus.tw/events/{$event->id}", false)
            ->assertSee("https://truth-shield-api.otus.tw/share/events/{$event->id}/image.png", false)
            ->assertSee(Str::limit($event->summary, 140), false);
    }

    public function test_event_share_preview_image_is_png(): void
    {
        $event = NewsEvent::query()->create([
            'name' => '事件分享縮圖',
            'slug' => 'event-share-image',
            'summary' => '這張圖片會用在 Facebook 與 Threads 分享預覽。',
            'last_activity_at' => now(),
        ]);

        $response = $this->get("/share/events/{$event->id}/image.png")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }
}
