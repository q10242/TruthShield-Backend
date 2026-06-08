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
            'resource_circulation_law_event' => $this->resourceCirculationLawEvent($execute),
            'vasp_stablecoin_regulation_event' => $this->vaspStablecoinRegulationEvent($execute),
            'east_coast_maritime_enforcement_event' => $this->eastCoastMaritimeEnforcementEvent($execute),
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

    private function resourceCirculationLawEvent(bool $execute): array
    {
        $summary = [
            'task' => 'resource_circulation_law_event',
            'execute' => $execute,
            'event' => null,
            'items_created' => 0,
            'timeline_created' => 0,
        ];

        if (! $execute) {
            $summary['planned'] = [
                'event_slug' => 'resource-circulation-law-2026',
                'source_count' => count($this->resourceCirculationLawSources()),
            ];

            return $summary;
        }

        return DB::transaction(function () use ($summary): array {
            $admin = $this->maintenanceUser();
            $sources = $this->resourceCirculationLawSources();
            $primaryNews = $this->ensureNewsUrl($sources[0]['url'], $sources[0]['title']);
            $slug = 'resource-circulation-law-2026';
            $event = NewsEvent::query()->where('slug', $slug)->first();
            $created = false;

            $attributes = [
                'created_by' => $admin->id,
                'primary_news_url_id' => $primaryNews->id,
                'name' => '資源循環推動法三讀與循環治理制度建置',
                'slug' => $slug,
                'summary' => implode("\n", [
                    '整理資源回收再利用法修正、更名為資源循環推動法的立法過程與後續治理制度。',
                    '此事件聚焦全生命週期管理、國家資源循環計畫、跨部會推動會、綠色設計、循環採購與創新實驗沙盒等公開政策資訊。',
                    '持續追蹤重點：子法與準則公告、中央/地方分工、產業轉型配套、非法棄置與廢棄物清理法銜接。',
                ]),
                'primary_category' => 'public_policy',
                'tags' => ['environment', 'law_reform'],
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
                    'TruthShield AI maintenance: created resource circulation law event from public sources.',
                    'event.created',
                    "營運 AI 建立事件「{$event->name}」，整理資源循環推動法三讀與後續治理制度公開來源。",
                    ['source' => 'truthshield:maintain-events', 'task' => 'resource_circulation_law_event'],
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
                        'TruthShield AI maintenance: refreshed resource circulation law event metadata.',
                        'event.metadata_updated',
                        "營運 AI 更新事件「{$event->name}」分類、標籤與追蹤狀態。",
                        ['source' => 'truthshield:maintain-events', 'task' => 'resource_circulation_law_event'],
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
                        'item_type' => $source['item_type'] ?? 'news',
                        'title' => $source['title'],
                        'summary' => $source['summary'],
                        'source_url' => $source['url'],
                    ],
                );

                if ($item->wasRecentlyCreated) {
                    $itemsCreated++;
                    $this->logItemChange($event, $admin, $item, 'Attached resource circulation law source item to event.');
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
                        'source_type' => $source['source_type'] ?? 'news',
                    ],
                );

                if ($timeline->wasRecentlyCreated) {
                    $timelineCreated++;
                    $this->logItemChange($event, $admin, $timeline, 'Pinned resource circulation law public-source timeline entry.');
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
                        'task' => 'resource_circulation_law_event',
                        'items_created' => $itemsCreated,
                        'timeline_created' => $timelineCreated,
                    ],
                ]);
            }

            $event->forceFill(['last_activity_at' => now()])->save();

            $summary['event'] = [
                'id' => $event->id,
                'slug' => $event->slug,
                'status' => $created ? 'created' : 'updated',
            ];
            $summary['items_created'] = $itemsCreated;
            $summary['timeline_created'] = $timelineCreated;

            return $summary;
        });
    }

    private function resourceCirculationLawSources(): array
    {
        return [
            [
                'title' => '行政院通過環境雙法修正草案並函請立法院審議',
                'summary' => '行政院國家永續發展委員會發布，行政院會通過資源回收再利用法修正草案與廢棄物清理法部分條文修正草案，並將法案名稱修正為資源循環推動法。',
                'occurred_at' => '2026-04-09 00:00:00',
                'url' => 'https://ncsd.ndc.gov.tw/Fore/News_detail/7d7b86b1-36d6-4697-b905-18df5777db54',
                'source_type' => 'official',
                'item_type' => 'official_record',
            ],
            [
                'title' => '立法院三讀通過資源循環推動法',
                'summary' => '中央社報導，立法院三讀通過資源循環推動法，修法重點包括全生命週期管理、國家資源循環計畫、跨部會分工、綠色設計、循環採購與創新實驗沙盒。',
                'occurred_at' => '2026-06-02 00:00:00',
                'url' => 'https://www.cna.com.tw/news/aipl/202606020060.aspx',
            ],
            [
                'title' => '環境部說明循環法三讀後由廢棄物管理邁向資源循環',
                'summary' => '中央社報導環境部說明，本法建立資源全生命週期管理制度，後續仍需追蹤子法、推動會與實際執行配套。',
                'occurred_at' => '2026-06-02 00:00:00',
                'url' => 'https://www.cna.com.tw/news/ahel/202606020134.aspx',
            ],
            [
                'title' => '環境部發布資源循環推動法三讀通過新聞稿',
                'summary' => '環境部新聞稿表示，資源循環推動法三讀通過，作為落實資源循環零廢棄及臺灣2050淨零排放路徑關鍵戰略的重要法制基礎。',
                'occurred_at' => '2026-06-02 00:00:00',
                'url' => 'https://enews.moenv.gov.tw/page/3b3c62c78849F32F/49f0b94e-1173-480e-a41f-b1578905c692',
                'source_type' => 'official',
                'item_type' => 'official_record',
            ],
        ];
    }

    private function vaspStablecoinRegulationEvent(bool $execute): array
    {
        $summary = [
            'task' => 'vasp_stablecoin_regulation_event',
            'execute' => $execute,
            'event' => null,
            'items_created' => 0,
            'timeline_created' => 0,
        ];

        if (! $execute) {
            $summary['planned'] = [
                'event_slug' => 'vasp-stablecoin-regulation-2026',
                'source_count' => count($this->vaspStablecoinRegulationSources()),
            ];

            return $summary;
        }

        return DB::transaction(function () use ($summary): array {
            $admin = $this->maintenanceUser();
            $sources = $this->vaspStablecoinRegulationSources();
            $primaryNews = $this->ensureNewsUrl($sources[0]['url'], $sources[0]['title']);
            $slug = 'vasp-stablecoin-regulation-2026';
            $event = NewsEvent::query()->where('slug', $slug)->first();
            $created = false;

            $attributes = [
                'created_by' => $admin->id,
                'primary_news_url_id' => $primaryNews->id,
                'name' => '虛擬資產服務法草案與穩定幣監理',
                'slug' => $slug,
                'summary' => implode("\n", [
                    '整理虛擬資產服務法草案從行政院會通過到立法院初審的公開進度，聚焦 VASP 許可制、穩定幣發行準備資產、客戶資產分離保管、資安與反詐欺/市場操縱罰則。',
                    '此事件不判定個別虛擬資產、交易所或穩定幣風險；重點是讓讀者追蹤法案文字、主管機關說明、立法院審查與後續子法配套。',
                    '持續追蹤重點：三讀時程、金管會與央行配套、業者過渡期、海外業者落地規範、保管業務試辦與交易人保護。',
                ]),
                'primary_category' => 'finance',
                'tags' => ['law_reform', 'platform_governance', 'fraud'],
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
                    'TruthShield AI maintenance: created VASP and stablecoin regulation event from public sources.',
                    'event.created',
                    "營運 AI 建立事件「{$event->name}」，整理虛擬資產服務法草案與穩定幣監理公開來源。",
                    ['source' => 'truthshield:maintain-events', 'task' => 'vasp_stablecoin_regulation_event'],
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
                        'TruthShield AI maintenance: refreshed VASP and stablecoin regulation event metadata.',
                        'event.metadata_updated',
                        "營運 AI 更新事件「{$event->name}」分類、標籤與追蹤狀態。",
                        ['source' => 'truthshield:maintain-events', 'task' => 'vasp_stablecoin_regulation_event'],
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
                        'item_type' => $source['item_type'] ?? 'news',
                        'title' => $source['title'],
                        'summary' => $source['summary'],
                        'source_url' => $source['url'],
                    ],
                );

                if ($item->wasRecentlyCreated) {
                    $itemsCreated++;
                    $this->logItemChange($event, $admin, $item, 'Attached VASP and stablecoin regulation source item to event.');
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
                        'source_type' => $source['source_type'] ?? 'news',
                    ],
                );

                if ($timeline->wasRecentlyCreated) {
                    $timelineCreated++;
                    $this->logItemChange($event, $admin, $timeline, 'Pinned VASP and stablecoin regulation public-source timeline entry.');
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
                        'task' => 'vasp_stablecoin_regulation_event',
                        'items_created' => $itemsCreated,
                        'timeline_created' => $timelineCreated,
                    ],
                ]);
            }

            $event->forceFill(['last_activity_at' => now()])->save();

            $summary['event'] = [
                'id' => $event->id,
                'slug' => $event->slug,
                'status' => $created ? 'created' : 'updated',
            ];
            $summary['items_created'] = $itemsCreated;
            $summary['timeline_created'] = $timelineCreated;

            return $summary;
        });
    }

    private function vaspStablecoinRegulationSources(): array
    {
        return [
            [
                'title' => '行政院會通過虛擬資產服務法草案',
                'summary' => '中央社報導，行政院會通過虛擬資產服務法草案，明定穩定幣發行依據、加強虛擬資產交易安全、推動虛擬資產保管業務試辦，並對詐欺或操縱行為訂出罰則。',
                'occurred_at' => '2026-04-02 16:53:00',
                'url' => 'https://www.cna.com.tw/news/aipl/202604020237.aspx',
            ],
            [
                'title' => '行政院發布虛擬資產服務法草案官方說明',
                'summary' => '行政院國家永續發展委員會發布，草案對 VASP 與穩定幣發行人建立監理框架，規範財務健全度、資產分離保管、防止不公正交易等行為。',
                'occurred_at' => '2026-04-02 00:00:00',
                'url' => 'https://ncsd.ndc.gov.tw/Fore/News_detail/e4f3d9fe-8f88-4056-b738-92c56dc4996c',
                'source_type' => 'official',
                'item_type' => 'official_record',
            ],
            [
                'title' => '立法院初審通過虛擬資產服務法草案',
                'summary' => '中央社報導，立法院財政委員會初審通過草案，強化 VASP 監理，須取得主管機關許可始得營業；穩定幣發行人應設置並維持十足準備資產，並與自有財產分離。',
                'occurred_at' => '2026-06-03 18:54:00',
                'url' => 'https://www.cna.com.tw/news/aipl/202606030313.aspx',
            ],
        ];
    }

    private function eastCoastMaritimeEnforcementEvent(bool $execute): array
    {
        $summary = [
            'task' => 'east_coast_maritime_enforcement_event',
            'execute' => $execute,
            'event' => null,
            'items_created' => 0,
            'timeline_created' => 0,
        ];

        if (! $execute) {
            $summary['planned'] = [
                'event_slug' => 'east-coast-maritime-enforcement-2026',
                'source_count' => count($this->eastCoastMaritimeEnforcementSources()),
            ];

            return $summary;
        }

        return DB::transaction(function () use ($summary): array {
            $admin = $this->maintenanceUser();
            $sources = $this->eastCoastMaritimeEnforcementSources();
            $primaryNews = $this->ensureNewsUrl($sources[0]['url'], $sources[0]['title']);
            $slug = 'east-coast-maritime-enforcement-2026';
            $event = NewsEvent::query()->where('slug', $slug)->first();
            $created = false;

            $attributes = [
                'created_by' => $admin->id,
                'primary_news_url_id' => $primaryNews->id,
                'name' => '中國宣稱台灣東部海域執法與海巡應處',
                'slug' => $slug,
                'summary' => implode("\n", [
                    '整理中國海警與交通運輸部宣稱在台灣以東海域執法、台灣海巡署與國防部回應、以及日菲海域劃界敘事牽動的公開來源時間線。',
                    '此事件不判定軍事或外交結果；重點是讓讀者核對各方聲明、官方應處、媒體轉述與可能的認知戰/法律戰敘事差異。',
                    '持續追蹤重點：中國公務船後續航跡、海巡艦艇監控與驅離紀錄、國防部/陸委會/外交部說明、日菲官方談判進度與假圖求證。',
                ]),
                'primary_category' => 'international',
                'tags' => ['national_security', 'cross_strait', 'media_ethics'],
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
                    'TruthShield AI maintenance: created east coast maritime enforcement event from public sources.',
                    'event.created',
                    "營運 AI 建立事件「{$event->name}」，整理台灣東部海域執法宣稱與海巡應處公開來源。",
                    ['source' => 'truthshield:maintain-events', 'task' => 'east_coast_maritime_enforcement_event'],
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
                        'TruthShield AI maintenance: refreshed east coast maritime enforcement event metadata.',
                        'event.metadata_updated',
                        "營運 AI 更新事件「{$event->name}」分類、標籤與追蹤狀態。",
                        ['source' => 'truthshield:maintain-events', 'task' => 'east_coast_maritime_enforcement_event'],
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
                        'item_type' => $source['item_type'] ?? 'news',
                        'title' => $source['title'],
                        'summary' => $source['summary'],
                        'source_url' => $source['url'],
                    ],
                );

                if ($item->wasRecentlyCreated) {
                    $itemsCreated++;
                    $this->logItemChange($event, $admin, $item, 'Attached east coast maritime enforcement source item to event.');
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
                        'source_type' => $source['source_type'] ?? 'news',
                    ],
                );

                if ($timeline->wasRecentlyCreated) {
                    $timelineCreated++;
                    $this->logItemChange($event, $admin, $timeline, 'Pinned east coast maritime enforcement public-source timeline entry.');
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
                        'task' => 'east_coast_maritime_enforcement_event',
                        'items_created' => $itemsCreated,
                        'timeline_created' => $timelineCreated,
                    ],
                ]);
            }

            $event->forceFill(['last_activity_at' => now()])->save();

            $summary['event'] = [
                'id' => $event->id,
                'slug' => $event->slug,
                'status' => $created ? 'created' : 'updated',
            ];
            $summary['items_created'] = $itemsCreated;
            $summary['timeline_created'] = $timelineCreated;

            return $summary;
        });
    }

    private function eastCoastMaritimeEnforcementSources(): array
    {
        return [
            [
                'title' => '中國海警稱在台灣以東海域執法巡查',
                'summary' => '中央社報導中國海警局宣稱在台灣以東海域展開執法巡查，並稱行動與日本、菲律賓海域劃界談判有關；這是事件敘事的起點之一。',
                'occurred_at' => '2026-06-01 10:49:00',
                'url' => 'https://www.cna.com.tw/news/acn/202606010047.aspx',
            ],
            [
                'title' => '海巡署駁斥中國交通運輸部東部海域專項執法宣稱',
                'summary' => '海巡署發布新聞稿，表示中國在台灣東部海域不享有主權權利，所謂專項執法行動違反國際法且背離事實，並說明已部署艦艇應處。',
                'occurred_at' => '2026-06-07 00:00:00',
                'url' => 'https://www.cga.gov.tw/GipOpen/wSite/ct?ctNode=650&mp=999%2F&xItem=168064',
                'source_type' => 'official',
                'item_type' => 'official_record',
            ],
            [
                'title' => '公視整理中國稱將在台灣東部海域執法與台灣應處',
                'summary' => '公視報導整理中國交通運輸部宣稱東部海域執法、海巡署部署艦艇與行政院海洋政策說法，適合作為公開敘事差異與時間線來源。',
                'occurred_at' => '2026-06-07 00:00:00',
                'url' => 'https://news.pts.org.tw/article/811866',
            ],
            [
                'title' => '顧立雄稱中國宣稱東部海域執法是挑釁與認知戰',
                'summary' => '中央社轉載報導，國防部長顧立雄在立法院受訪時表示，中國宣稱台灣東部海域為執法區域是挑釁與認知戰，國防部與海巡會協調分工維護海域安全。',
                'occurred_at' => '2026-06-08 10:19:56',
                'url' => 'https://news.pchome.com.tw/politics/cna/20260608/index-17808851960442618001.html',
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
