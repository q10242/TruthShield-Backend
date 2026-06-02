<?php

namespace App\Services;

use App\Models\EventEditLog;
use App\Models\ModerationEvent;
use App\Models\NewsEvent;
use App\Models\NewsEventItem;
use App\Models\NewsEventTimelineEntry;
use App\Models\NewsUrl;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EventMaintenanceService
{
    public function __construct(private readonly UrlFingerprintService $fingerprints)
    {
    }

    public function run(string $task, bool $execute = false): array
    {
        return match ($task) {
            'nuclear_restart_and_tag_backfill' => $this->nuclearRestartAndTagBackfill($execute),
            default => throw new InvalidArgumentException("Unsupported maintenance task: {$task}"),
        };
    }

    private function nuclearRestartAndTagBackfill(bool $execute): array
    {
        $summary = [
            'task' => 'nuclear_restart_and_tag_backfill',
            'execute' => $execute,
            'tag_updates' => [],
            'event' => null,
            'items_created' => 0,
            'timeline_created' => 0,
        ];

        if (! $execute) {
            $summary['planned'] = [
                'tag_update_event_ids' => array_keys($this->eventTagBackfillMap()),
                'event_slug' => 'nuclear-restart-review-energy-policy-2026',
            ];

            return $summary;
        }

        return DB::transaction(function () use ($summary): array {
            $admin = $this->maintenanceUser();

            foreach ($this->eventTagBackfillMap() as $eventId => $metadata) {
                $event = NewsEvent::query()->find($eventId);
                if (! $event) {
                    $summary['tag_updates'][] = ['id' => $eventId, 'status' => 'missing'];
                    continue;
                }

                $before = $event->toArray();
                $event->forceFill([
                    'primary_category' => $metadata['primary_category'] ?? $event->primary_category,
                    'tags' => $metadata['tags'],
                    'progress_status' => $metadata['progress_status'] ?? $event->progress_status,
                    'last_activity_at' => now(),
                ])->save();

                if ($this->changed($before, $event->fresh()->toArray())) {
                    $this->logEventChange(
                        $event->fresh(),
                        $admin,
                        'updated',
                        $before,
                        $event->fresh()->toArray(),
                        'TruthShield AI maintenance: backfilled precise event taxonomy tags.',
                        'event.metadata_updated',
                        "營運 AI 維護事件「{$event->name}」分類與標籤，補上較精準的公開議題標籤。",
                        [
                            'source' => 'truthshield:maintain-events',
                            'task' => 'nuclear_restart_and_tag_backfill',
                            'tags' => $metadata['tags'],
                        ],
                    );
                    $summary['tag_updates'][] = ['id' => $event->id, 'status' => 'updated', 'tags' => $metadata['tags']];
                } else {
                    $summary['tag_updates'][] = ['id' => $event->id, 'status' => 'unchanged', 'tags' => $metadata['tags']];
                }
            }

            $eventSummary = $this->upsertNuclearRestartEvent($admin);
            $summary['event'] = $eventSummary['event'];
            $summary['items_created'] = $eventSummary['items_created'];
            $summary['timeline_created'] = $eventSummary['timeline_created'];

            return $summary;
        });
    }

    private function eventTagBackfillMap(): array
    {
        return [
            7 => [
                'primary_category' => 'public_policy',
                'tags' => ['public_construction', 'procurement'],
            ],
            21 => [
                'primary_category' => 'legal_case',
                'tags' => ['corruption', 'local_politics'],
            ],
            22 => [
                'primary_category' => 'legal_case',
                'tags' => ['corruption', 'local_politics'],
            ],
            25 => [
                'primary_category' => 'public_policy',
                'tags' => ['public_construction', 'procurement', 'law_reform'],
            ],
            29 => [
                'primary_category' => 'legal_case',
                'tags' => ['local_politics', 'election'],
            ],
            31 => [
                'primary_category' => 'public_policy',
                'tags' => ['public_construction', 'procurement'],
                'progress_status' => 'disputed',
            ],
        ];
    }

    private function upsertNuclearRestartEvent(User $admin): array
    {
        $slug = 'nuclear-restart-review-energy-policy-2026';
        $sources = $this->nuclearRestartSources();
        $primaryNews = $this->ensureNewsUrl($sources[0]['url'], $sources[0]['title']);
        $event = NewsEvent::query()->where('slug', $slug)->first();
        $created = false;

        $attributes = [
            'created_by' => $admin->id,
            'primary_news_url_id' => $primaryNews->id,
            'name' => '核二核三再運轉審查與能源政策爭議',
            'slug' => $slug,
            'summary' => implode("\n", [
                '整理核二、核三再運轉評估、核安會審查、政府能源政策原則、產業與地方/民間回應的公開來源時間線。',
                '此事件只彙整公開資料與待追蹤節點，不判定核電是否應重啟；重點放在審查程序、核安、核廢、供電與社會共識。',
                '可追蹤任務：補核安會審查文件、台電地方說明會資料、經濟部/台電正式送件進度、支持與反對方可驗證說法。',
            ]),
            'primary_category' => 'public_policy',
            'tags' => ['energy', 'nuclear_safety', 'law_reform', 'environment'],
            'progress_status' => 'tracking',
            'status' => 'active',
            'is_disputed' => false,
            'last_activity_at' => now(),
        ];

        if (! $event) {
            $event = NewsEvent::query()->create($attributes);
            $created = true;
            $this->logEventChange(
                $event,
                $admin,
                'created',
                null,
                $event->toArray(),
                'TruthShield AI maintenance: created nuclear restart policy event from public sources.',
                'event.created',
                "營運 AI 建立事件「{$event->name}」，整理核二核三再運轉審查與能源政策公開來源。",
                ['source' => 'truthshield:maintain-events', 'task' => 'nuclear_restart_and_tag_backfill'],
            );
        } else {
            $before = $event->toArray();
            $event->forceFill($attributes)->save();
            if ($this->changed($before, $event->fresh()->toArray())) {
                $this->logEventChange(
                    $event->fresh(),
                    $admin,
                    'updated',
                    $before,
                    $event->fresh()->toArray(),
                    'TruthShield AI maintenance: refreshed nuclear restart policy event metadata.',
                    'event.metadata_updated',
                    "營運 AI 更新事件「{$event->name}」分類、標籤與追蹤狀態。",
                    ['source' => 'truthshield:maintain-events', 'task' => 'nuclear_restart_and_tag_backfill'],
                );
            }
        }

        $itemsCreated = 0;
        $timelineCreated = 0;

        foreach ($sources as $source) {
            $newsUrl = $this->ensureNewsUrl($source['url'], $source['title']);

            $item = NewsEventItem::query()->firstOrCreate(
                ['news_event_id' => $event->id, 'news_url_id' => $newsUrl->id],
                [
                    'created_by' => $admin->id,
                    'item_type' => 'news',
                    'title' => $source['title'],
                    'summary' => $source['summary'],
                    'source_url' => $source['url'],
                ],
            );

            if ($item->wasRecentlyCreated) {
                $itemsCreated++;
                $this->logItemChange($event, $admin, $item, 'Attached source item to event.');
            }

            $timeline = NewsEventTimelineEntry::query()->firstOrCreate(
                [
                    'news_event_id' => $event->id,
                    'source_url' => $source['url'],
                    'title' => $source['title'],
                ],
                [
                    'news_url_id' => $newsUrl->id,
                    'created_by' => $admin->id,
                    'entry_type' => 'manual',
                    'summary' => $source['summary'],
                    'occurred_at' => $source['occurred_at'],
                    'source_type' => 'news',
                ],
            );

            if ($timeline->wasRecentlyCreated) {
                $timelineCreated++;
                $this->logItemChange($event, $admin, $timeline, 'Pinned public-source timeline entry.');
            }
        }

        if ($itemsCreated > 0 || $timelineCreated > 0 || $created) {
            ModerationEvent::query()->create([
                'user_id' => $admin->id,
                'event_type' => 'event.timeline_maintained',
                'subject_type' => NewsEvent::class,
                'subject_id' => $event->id,
                'public_reason' => "營運 AI 維護事件「{$event->name}」來源與時間線，保留公開來源 URL 與摘要。",
                'metadata' => [
                    'source' => 'truthshield:maintain-events',
                    'task' => 'nuclear_restart_and_tag_backfill',
                    'items_created' => $itemsCreated,
                    'timeline_created' => $timelineCreated,
                ],
            ]);
        }

        $event->forceFill(['last_activity_at' => now()])->save();

        return [
            'event' => [
                'id' => $event->id,
                'slug' => $event->slug,
                'status' => $created ? 'created' : 'updated',
            ],
            'items_created' => $itemsCreated,
            'timeline_created' => $timelineCreated,
        ];
    }

    private function nuclearRestartSources(): array
    {
        return [
            [
                'title' => '台電認定核二、核三具再運轉可行性',
                'summary' => '公視報導，經濟部核定台電現況評估，初步認定核二、核三具有再運轉可行性，並說明安全檢查與送審時程。',
                'occurred_at' => '2025-11-28 00:00:00',
                'url' => 'https://news.pts.org.tw/article/783431',
            ],
            [
                'title' => '核三再運轉審查時程與核安會程序受立院關注',
                'summary' => '公視報導，核安會與經濟部在立法院說明核三再運轉計畫送審、程序審查與實質審查可能時程。',
                'occurred_at' => '2026-03-18 00:00:00',
                'url' => 'https://news.pts.org.tw/article/799507',
            ],
            [
                'title' => '總統稱核二、核三具重啟條件並將送核安會審議',
                'summary' => '中央社報導，總統賴清德表示立法院通過核管法後，政府依法行政，台電準備將重啟計畫送核安會審議。',
                'occurred_at' => '2026-03-21 00:00:00',
                'url' => 'https://www.cna.com.tw/news/afe/202603210102.aspx',
            ],
            [
                'title' => '行政院重申核安、核廢與社會共識三原則',
                'summary' => '中央社報導，行政院長卓榮泰回應核能重啟討論，重申核安、核廢與社會共識等原則。',
                'occurred_at' => '2026-03-24 00:00:00',
                'url' => 'https://www.cna.com.tw/news/aipl/202603240103.aspx',
            ],
            [
                'title' => '核三再運轉審查與地方說明會資料公開受質詢',
                'summary' => '公視報導，核安會在立法院說明核三再運轉計畫書審查進度，台電表示會整理公布說明會資料。',
                'occurred_at' => '2026-05-25 00:00:00',
                'url' => 'https://news.pts.org.tw/article/809859',
            ],
        ];
    }

    private function ensureNewsUrl(string $url, string $title): NewsUrl
    {
        $fingerprint = $this->fingerprints->fingerprint($url);

        return NewsUrl::query()->firstOrCreate(
            ['hash' => $fingerprint['hash']],
            [
                'original_url' => $fingerprint['original_url'],
                'normalized_url' => $fingerprint['normalized_url'],
                'title_snapshot' => Str::limit($title, 255, ''),
                'voting_closes_at' => now()->addHours(72),
            ],
        );
    }

    private function maintenanceUser(): User
    {
        return User::query()
            ->where('is_admin', true)
            ->orderByDesc('trust_score')
            ->orderBy('id')
            ->firstOrFail();
    }

    private function changed(?array $before, array $after): bool
    {
        if ($before === null) {
            return true;
        }

        $fields = ['primary_category', 'tags', 'progress_status', 'status', 'is_disputed', 'summary', 'primary_news_url_id'];

        foreach ($fields as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function logEventChange(
        NewsEvent $event,
        User $admin,
        string $action,
        ?array $before,
        array $after,
        string $editReason,
        string $eventType,
        string $publicReason,
        array $metadata = [],
    ): void {
        EventEditLog::query()->create([
            'news_event_id' => $event->id,
            'user_id' => $admin->id,
            'action' => $action,
            'subject_type' => 'NewsEvent',
            'subject_id' => $event->id,
            'before' => $before,
            'after' => $after,
            'reason' => $editReason,
            'is_public' => true,
        ]);

        ModerationEvent::query()->create([
            'user_id' => $admin->id,
            'event_type' => $eventType,
            'subject_type' => NewsEvent::class,
            'subject_id' => $event->id,
            'public_reason' => $publicReason,
            'metadata' => $metadata,
        ]);
    }

    private function logItemChange(NewsEvent $event, User $admin, object $subject, string $reason): void
    {
        EventEditLog::query()->create([
            'news_event_id' => $event->id,
            'user_id' => $admin->id,
            'action' => 'created',
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'before' => null,
            'after' => $subject->toArray(),
            'reason' => $reason,
            'is_public' => true,
        ]);
    }
}
