<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityTask;
use App\Models\EventEditLog;
use App\Models\EventEntity;
use App\Models\EventRelationship;
use App\Models\Evidence;
use App\Models\ModerationEvent;
use App\Models\NewsEvent;
use App\Models\NewsEventItem;
use App\Models\NewsEventTimelineEntry;
use App\Models\NewsUrl;
use App\Models\OfficialResponse;
use App\Models\TrustedEvidenceSource;
use App\Services\UrlFingerprintService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class EventController extends Controller
{
    private const HIGH_RISK_RELATIONSHIPS = [
        'funded_by',
        'affiliated_with',
        'conflict_of_interest',
        'accuses',
        '利益關係',
        '資助',
        '隸屬',
        '指控',
    ];

    public function index(Request $request, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'news_url' => ['nullable', 'url', 'max:4096'],
            'status' => ['nullable', 'string', 'max:40'],
            'sort' => ['nullable', 'string', Rule::in(['updated', 'created', 'views', 'recent'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $newsUrl = null;
        if ($validated['news_url'] ?? null) {
            try {
                $fingerprint = $fingerprints->fingerprint($validated['news_url']);
                $newsUrl = NewsUrl::query()->where('hash', $fingerprint['hash'])->first();
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        }

        $query = NewsEvent::query()
            ->withCount(['items', 'timelineEntries', 'entities', 'relationships'])
            ->with('primaryNewsUrl:id,title_snapshot,normalized_url')
            ->when(($validated['news_url'] ?? null) && ! $newsUrl, fn ($builder) => $builder->whereRaw('1 = 0'))
            ->when($newsUrl, function ($builder) use ($newsUrl): void {
                $builder->where(function ($inner) use ($newsUrl): void {
                    $inner->where('primary_news_url_id', $newsUrl->id)
                        ->orWhereHas('items', fn ($item) => $item->where('news_url_id', $newsUrl->id))
                        ->orWhereHas('timelineEntries', fn ($entry) => $entry->where('news_url_id', $newsUrl->id))
                        ->orWhereHas('relationships', fn ($relationship) => $relationship->where('news_url_id', $newsUrl->id));
                });
            })
            ->when($validated['q'] ?? null, function ($builder, string $q): void {
                $builder->where(function ($inner) use ($q): void {
                    $inner->where('name', 'ilike', "%{$q}%")
                        ->orWhere('summary', 'ilike', "%{$q}%")
                        ->orWhereHas('entities', fn ($entity) => $entity->where('name', 'ilike', "%{$q}%"))
                        ->orWhereHas('items.newsUrl', fn ($news) => $news->where('title_snapshot', 'ilike', "%{$q}%"));
                });
            })
            ->where('status', $validated['status'] ?? 'active');

        match ($validated['sort'] ?? 'updated') {
            'created' => $query->latest(),
            'views'   => $query->orderByDesc('view_count')->latest(),
            'recent'  => $query->orderByDesc('last_viewed_at')->latest(),
            default   => $query->orderByDesc('last_activity_at')->latest(),
        };

        $limit = (int) ($validated['limit'] ?? 30);

        return response()->json([
            'data' => $query->limit($limit)->get()->map(fn (NewsEvent $event) => $this->eventPayload($event)),
            'meta' => ['limit' => $limit, 'sort' => $validated['sort'] ?? 'updated'],
        ]);
    }

    public function store(Request $request, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'news_url' => ['required', 'url', 'max:4096'],
            'title_snapshot' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $newsUrl = $this->ensureNewsUrl($fingerprints, $validated['news_url'], $validated['title_snapshot'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $event = DB::transaction(function () use ($request, $validated, $newsUrl): NewsEvent {
            $event = NewsEvent::query()->create([
                'created_by' => $request->user()?->id,
                'primary_news_url_id' => $newsUrl->id,
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
                'summary' => $validated['summary'] ?? null,
                'last_activity_at' => now(),
            ]);

            $item = $this->attachNewsItem($event, $newsUrl, $request->user()?->id, [
                'title' => $newsUrl->title_snapshot,
                'summary' => $validated['summary'] ?? null,
            ]);

            $this->logEdit($event, $request, 'created', $event, null, $event->toArray(), 'Created event from news URL.');
            $this->logEdit($event, $request, 'attached', $item, null, $item->toArray(), 'Attached primary news URL.');
            $this->recordModerationEvent($event, $request, 'event.created', "社群建立事件「{$event->name}」。");

            return $event;
        });

        return response()->json($this->showPayload($event->fresh()), 201);
    }

    public function show(NewsEvent $event): JsonResponse
    {
        $event->increment('view_count');
        $event->forceFill(['last_viewed_at' => now()])->save();

        return response()->json($this->showPayload($event->fresh()));
    }

    public function storeItem(Request $request, NewsEvent $event, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'item_type' => ['required', 'string', Rule::in(['news', 'evidence', 'official_response', 'snapshot', 'external'])],
            'news_url' => ['nullable', 'url', 'max:4096'],
            'news_url_id' => ['nullable', 'integer', 'exists:news_urls,id'],
            'evidence_id' => ['nullable', 'integer', 'exists:evidences,id'],
            'official_response_id' => ['nullable', 'integer', 'exists:official_responses,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'source_url' => ['nullable', 'url', 'max:4096'],
        ]);

        $newsUrl = null;
        if (($validated['item_type'] === 'news' || ($validated['news_url'] ?? null)) && ($validated['news_url'] ?? null)) {
            $newsUrl = $this->ensureNewsUrl($fingerprints, $validated['news_url'], $validated['title'] ?? null);
        } elseif ($validated['news_url_id'] ?? null) {
            $newsUrl = NewsUrl::query()->find($validated['news_url_id']);
        }

        $attributes = [
                'evidence_id' => $validated['evidence_id'] ?? null,
                'official_response_id' => $validated['official_response_id'] ?? null,
                'created_by' => $request->user()?->id,
                'item_type' => $validated['item_type'],
                'title' => $validated['title'] ?? $newsUrl?->title_snapshot,
                'summary' => $validated['summary'] ?? null,
                'source_url' => $validated['source_url'] ?? $newsUrl?->normalized_url,
        ];
        $item = $newsUrl
            ? NewsEventItem::query()->firstOrCreate(['news_event_id' => $event->id, 'news_url_id' => $newsUrl->id], $attributes)
            : NewsEventItem::query()->create(['news_event_id' => $event->id, ...$attributes]);

        $event->forceFill(['last_activity_at' => now()])->save();
        $this->logEdit($event, $request, 'attached', $item, null, $item->toArray(), 'Attached item to event.');

        return response()->json(['data' => $item->load(['newsUrl', 'evidence', 'officialResponse'])], 201);
    }

    public function timeline(NewsEvent $event): JsonResponse
    {
        return response()->json([
            'data' => $event->timelineEntries()
                ->with(['newsUrl:id,title_snapshot,normalized_url', 'evidence:id,url,preview_url', 'creator:id,name,display_name,trust_score'])
                ->orderBy('occurred_at')
                ->get(),
        ]);
    }

    public function storeTimeline(Request $request, NewsEvent $event, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['required', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
            'source_type' => ['required', 'string', Rule::in(['news', 'evidence', 'official_response', 'external'])],
            'source_url' => ['required_without:evidence_id,official_response_id', 'nullable', 'url', 'max:4096'],
            'news_url' => ['nullable', 'url', 'max:4096'],
            'evidence_id' => ['nullable', 'integer', 'exists:evidences,id'],
            'official_response_id' => ['nullable', 'integer', 'exists:official_responses,id'],
        ]);

        $newsUrl = null;
        if (($validated['news_url'] ?? null) || (($validated['source_type'] ?? null) === 'news' && ($validated['source_url'] ?? null))) {
            $newsUrl = $this->ensureNewsUrl($fingerprints, $validated['news_url'] ?? $validated['source_url'], $validated['title']);
            $this->attachNewsItem($event, $newsUrl, $request->user()?->id, [
                'title' => $newsUrl->title_snapshot ?: $validated['title'],
                'summary' => $validated['summary'],
            ]);
        }

        $entry = NewsEventTimelineEntry::query()->create([
            'news_event_id' => $event->id,
            'news_url_id' => $newsUrl?->id,
            'evidence_id' => $validated['evidence_id'] ?? null,
            'official_response_id' => $validated['official_response_id'] ?? null,
            'created_by' => $request->user()?->id,
            'entry_type' => 'manual',
            'title' => $validated['title'],
            'summary' => $validated['summary'],
            'occurred_at' => $validated['occurred_at'],
            'source_url' => $validated['source_url'] ?? $newsUrl?->normalized_url,
            'source_type' => $validated['source_type'],
        ]);

        $event->forceFill(['last_activity_at' => now()])->save();
        $this->logEdit($event, $request, 'created', $entry, null, $entry->toArray(), 'Pinned timeline entry.');

        return response()->json(['data' => $entry->load(['newsUrl', 'evidence', 'creator'])], 201);
    }

    public function updateTimeline(Request $request, NewsEvent $event, NewsEventTimelineEntry $entry, UrlFingerprintService $fingerprints): JsonResponse
    {
        $this->abortUnlessTimelineBelongsToEvent($event, $entry);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['required', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
            'source_type' => ['required', 'string', Rule::in(['news', 'evidence', 'official_response', 'external'])],
            'source_url' => ['required_without:evidence_id,official_response_id', 'nullable', 'url', 'max:4096'],
            'news_url' => ['nullable', 'url', 'max:4096'],
            'evidence_id' => ['nullable', 'integer', 'exists:evidences,id'],
            'official_response_id' => ['nullable', 'integer', 'exists:official_responses,id'],
        ]);

        $newsUrl = null;
        if (($validated['news_url'] ?? null) || (($validated['source_type'] ?? null) === 'news' && ($validated['source_url'] ?? null))) {
            $newsUrl = $this->ensureNewsUrl($fingerprints, $validated['news_url'] ?? $validated['source_url'], $validated['title']);
            $this->attachNewsItem($event, $newsUrl, $request->user()?->id, [
                'title' => $newsUrl->title_snapshot ?: $validated['title'],
                'summary' => $validated['summary'],
            ]);
        }

        $before = $entry->toArray();
        $entry->forceFill([
            'news_url_id' => $newsUrl?->id,
            'evidence_id' => $validated['evidence_id'] ?? null,
            'official_response_id' => $validated['official_response_id'] ?? null,
            'title' => $validated['title'],
            'summary' => $validated['summary'],
            'occurred_at' => $validated['occurred_at'],
            'source_url' => $validated['source_url'] ?? $newsUrl?->normalized_url,
            'source_type' => $validated['source_type'],
        ])->save();

        $event->forceFill(['last_activity_at' => now()])->save();
        $this->logEdit($event, $request, 'updated', $entry, $before, $entry->fresh()->toArray(), 'Updated timeline entry.');

        return response()->json(['data' => $entry->fresh()->load(['newsUrl', 'evidence', 'creator'])]);
    }

    public function deleteTimeline(Request $request, NewsEvent $event, NewsEventTimelineEntry $entry): JsonResponse
    {
        $this->abortUnlessTimelineBelongsToEvent($event, $entry);

        $before = $entry->toArray();
        $entry->delete();
        $event->forceFill(['last_activity_at' => now()])->save();
        $this->logEdit($event, $request, 'deleted', $entry, $before, null, 'Deleted timeline entry.');

        return response()->json(['message' => 'Timeline entry deleted.']);
    }

    public function graph(NewsEvent $event): JsonResponse
    {
        return response()->json([
            'entities' => $event->entities()
                ->with(['outgoingRelationships', 'globalEntity:id,name,entity_type,description,wikipedia_url'])
                ->orderBy('entity_type')
                ->orderBy('name')
                ->get(),
            'relationships' => $event->relationships()
                ->with(['fromEntity:id,name,entity_type', 'toEntity:id,name,entity_type', 'newsUrl:id,title_snapshot,normalized_url', 'evidence:id,url,preview_url'])
                ->latest()
                ->get(),
        ]);
    }

    public function storeEntity(Request $request, NewsEvent $event): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'entity_type' => ['required', 'string', Rule::in(['person', 'organization'])],
            'aliases' => ['nullable', 'array', 'max:10'],
            'aliases.*' => ['string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'source_url' => ['nullable', 'url', 'max:4096'],
            'global_entity_id' => ['nullable', 'integer', 'exists:global_entities,id'],
        ]);

        $entity = EventEntity::query()->firstOrCreate(
            [
                'news_event_id' => $event->id,
                'entity_type' => $validated['entity_type'],
                'name' => $validated['name'],
            ],
            [
                'global_entity_id' => $validated['global_entity_id'] ?? null,
                'created_by' => $request->user()?->id,
                'aliases' => $validated['aliases'] ?? null,
                'description' => $validated['description'] ?? null,
                'source_url' => $validated['source_url'] ?? null,
            ],
        );

        // Link to global entity if not already set (e.g. entity already existed locally)
        if (! $entity->global_entity_id && isset($validated['global_entity_id'])) {
            $entity->forceFill(['global_entity_id' => $validated['global_entity_id']])->save();
        }

        $event->forceFill(['last_activity_at' => now()])->save();
        $this->logEdit($event, $request, 'created', $entity, null, $entity->toArray(), 'Created event entity.');

        return response()->json(['data' => $entity->load('globalEntity')], 201);
    }

    public function updateEntity(Request $request, NewsEvent $event, EventEntity $entity): JsonResponse
    {
        $this->abortUnlessEntityBelongsToEvent($event, $entity);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('event_entities', 'name')
                    ->where('news_event_id', $event->id)
                    ->where('entity_type', $request->input('entity_type', $entity->entity_type))
                    ->ignore($entity->id),
            ],
            'entity_type' => ['required', 'string', Rule::in(['person', 'organization'])],
            'aliases' => ['nullable', 'array', 'max:10'],
            'aliases.*' => ['string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'source_url' => ['nullable', 'url', 'max:4096'],
        ]);

        $before = $entity->toArray();
        $entity->forceFill([
            'name' => $validated['name'],
            'entity_type' => $validated['entity_type'],
            'aliases' => $validated['aliases'] ?? null,
            'description' => $validated['description'] ?? null,
            'source_url' => $validated['source_url'] ?? null,
        ])->save();

        $event->forceFill(['last_activity_at' => now()])->save();
        $this->logEdit($event, $request, 'updated', $entity, $before, $entity->fresh()->toArray(), 'Updated event entity.');

        return response()->json(['data' => $entity->fresh()]);
    }

    public function updateEntityPosition(Request $request, NewsEvent $event, EventEntity $entity): JsonResponse
    {
        $this->abortUnlessEntityBelongsToEvent($event, $entity);

        $validated = $request->validate([
            'x' => ['required', 'numeric', 'min:40', 'max:480'],
            'y' => ['required', 'numeric', 'min:40', 'max:400'],
        ]);

        $metadata = $entity->metadata ?? [];
        $metadata['graph_position'] = [
            'x' => round((float) $validated['x'], 2),
            'y' => round((float) $validated['y'], 2),
        ];

        $entity->forceFill(['metadata' => $metadata])->save();

        return response()->json(['data' => $entity->fresh()]);
    }

    public function deleteEntity(Request $request, NewsEvent $event, EventEntity $entity): JsonResponse
    {
        $this->abortUnlessEntityBelongsToEvent($event, $entity);

        $before = $entity->toArray();
        $relationships = EventRelationship::query()
            ->where('news_event_id', $event->id)
            ->where(fn ($query) => $query->where('from_entity_id', $entity->id)->orWhere('to_entity_id', $entity->id))
            ->get();

        foreach ($relationships as $relationship) {
            $relationshipBefore = $relationship->toArray();
            $relationship->delete();
            $this->logEdit($event, $request, 'deleted', $relationship, $relationshipBefore, null, "Deleted relationship while deleting entity {$entity->name}.");
        }

        $entity->delete();
        $event->forceFill(['last_activity_at' => now()])->save();
        $this->logEdit($event, $request, 'deleted', $entity, $before, null, 'Deleted event entity.');

        return response()->json(['message' => 'Entity deleted.', 'deleted_relationships' => $relationships->count()]);
    }

    public function mergeEntity(Request $request, NewsEvent $event, EventEntity $entity): JsonResponse
    {
        $this->abortUnlessEntityBelongsToEvent($event, $entity);

        $validated = $request->validate([
            'target_entity_id' => [
                'required',
                'integer',
                Rule::exists('event_entities', 'id')->where('news_event_id', $event->id),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ((int) $validated['target_entity_id'] === (int) $entity->id) {
            return response()->json([
                'message' => 'Cannot merge an entity into itself.',
                'errors' => ['target_entity_id' => ['Cannot merge an entity into itself.']],
            ], 422);
        }

        $target = EventEntity::query()
            ->where('news_event_id', $event->id)
            ->findOrFail($validated['target_entity_id']);

        $sourceBefore = $entity->toArray();
        $changedRelationships = collect();

        DB::transaction(function () use ($request, $event, $entity, $target, $sourceBefore, $validated, &$changedRelationships): void {
            $relationships = EventRelationship::query()
                ->where('news_event_id', $event->id)
                ->where(fn ($query) => $query->where('from_entity_id', $entity->id)->orWhere('to_entity_id', $entity->id))
                ->get();

            foreach ($relationships as $relationship) {
                $before = $relationship->toArray();
                $fromId = (int) $relationship->from_entity_id === (int) $entity->id ? $target->id : $relationship->from_entity_id;
                $toId = (int) $relationship->to_entity_id === (int) $entity->id ? $target->id : $relationship->to_entity_id;

                if ((int) $fromId === (int) $toId) {
                    $relationship->delete();
                    $this->logEdit($event, $request, 'deleted', $relationship, $before, null, "Deleted self-loop relationship while merging {$entity->name} into {$target->name}.");
                    continue;
                }

                $relationship->forceFill([
                    'from_entity_id' => $fromId,
                    'to_entity_id' => $toId,
                ])->save();
                $fresh = $relationship->fresh();
                $changedRelationships->push($fresh);
                $this->logEdit($event, $request, 'updated', $relationship, $before, $fresh->toArray(), "Moved relationship while merging {$entity->name} into {$target->name}.");
            }

            $metadata = array_merge($entity->metadata ?? [], [
                'merged_into_entity_id' => $target->id,
                'merged_into_entity_name' => $target->name,
                'merge_reason' => $validated['reason'] ?? null,
            ]);

            $entity->forceFill(['metadata' => $metadata])->save();
            $entity->delete();
            $event->forceFill(['last_activity_at' => now()])->save();
            $this->logEdit($event, $request, 'merged', $entity, $sourceBefore, null, "Merged entity {$entity->name} into {$target->name}.");
        });

        return response()->json([
            'message' => 'Entity merged.',
            'data' => $target->fresh(),
            'moved_relationships' => $changedRelationships->count(),
        ]);
    }

    public function storeRelationship(Request $request, NewsEvent $event, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'from_entity_id' => ['nullable', 'integer', Rule::exists('event_entities', 'id')->where('news_event_id', $event->id)],
            'from_entity_name' => ['required_without:from_entity_id', 'nullable', 'string', 'max:160'],
            'from_entity_type' => ['required_with:from_entity_name', 'nullable', Rule::in(['person', 'organization'])],
            'to_entity_id' => ['required', 'integer', Rule::exists('event_entities', 'id')->where('news_event_id', $event->id)],
            'relationship_type' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'source_url' => ['required', 'url', 'max:4096'],
            'source_type' => ['required', 'string', Rule::in(['news', 'evidence', 'official_response', 'external'])],
            'news_url' => ['nullable', 'url', 'max:4096'],
            'evidence_id' => ['nullable', 'integer', 'exists:evidences,id'],
            'official_response_id' => ['nullable', 'integer', 'exists:official_responses,id'],
            'is_bidirectional' => ['nullable', 'boolean'],
        ]);

        $fromEntity = isset($validated['from_entity_id'])
            ? EventEntity::query()->where('news_event_id', $event->id)->findOrFail($validated['from_entity_id'])
            : EventEntity::query()->firstOrCreate(
                [
                    'news_event_id' => $event->id,
                    'entity_type' => $validated['from_entity_type'],
                    'name' => $validated['from_entity_name'],
                ],
                ['created_by' => $request->user()?->id, 'source_url' => $validated['source_url']],
            );

        $newsUrl = null;
        if (($validated['source_type'] === 'news' || ($validated['news_url'] ?? null)) && ($validated['source_url'] ?? null)) {
            $newsUrl = $this->ensureNewsUrl($fingerprints, $validated['news_url'] ?? $validated['source_url']);
            $this->attachNewsItem($event, $newsUrl, $request->user()?->id, [
                'title' => $newsUrl->title_snapshot,
                'summary' => $validated['description'] ?? null,
            ]);
        }

        $isHighRisk = $this->isHighRiskRelationship($validated['relationship_type']);
        if ($isHighRisk && ! ($validated['evidence_id'] ?? null) && ! $this->isTrustedSourceUrl($validated['source_url'])) {
            return response()->json([
                'message' => 'High-risk relationships require an existing evidence record or a trusted source URL.',
                'errors' => [
                    'source_url' => ['High-risk relationships require an existing evidence record or a trusted source URL.'],
                ],
            ], 422);
        }

        $relationship = EventRelationship::query()->create([
            'news_event_id' => $event->id,
            'from_entity_id' => $fromEntity->id,
            'to_entity_id' => $validated['to_entity_id'],
            'news_url_id' => $newsUrl?->id,
            'evidence_id' => $validated['evidence_id'] ?? null,
            'official_response_id' => $validated['official_response_id'] ?? null,
            'created_by' => $request->user()?->id,
            'relationship_type' => $validated['relationship_type'],
            'description' => $validated['description'] ?? null,
            'source_url' => $validated['source_url'],
            'source_type' => $validated['source_type'],
            'is_high_risk' => $isHighRisk,
            'is_bidirectional' => $validated['is_bidirectional'] ?? false,
        ]);

        $event->forceFill([
            'last_activity_at' => now(),
            'controversy_score' => $isHighRisk ? $event->controversy_score + 1 : $event->controversy_score,
        ])->save();

        $this->logEdit($event, $request, 'created', $relationship, null, $relationship->toArray(), 'Pinned event relationship.');
        if ($isHighRisk) {
            $this->createRelationshipTask($event, $relationship);
        }

        return response()->json([
            'data' => $relationship->load(['fromEntity', 'toEntity', 'newsUrl', 'evidence']),
            'task_created' => $isHighRisk,
        ], 201);
    }

    public function updateRelationship(Request $request, NewsEvent $event, EventRelationship $relationship, UrlFingerprintService $fingerprints): JsonResponse
    {
        $this->abortUnlessRelationshipBelongsToEvent($event, $relationship);

        $validated = $request->validate([
            'from_entity_id' => ['required', 'integer', Rule::exists('event_entities', 'id')->where('news_event_id', $event->id)],
            'to_entity_id' => ['required', 'integer', Rule::exists('event_entities', 'id')->where('news_event_id', $event->id)],
            'relationship_type' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'source_url' => ['required', 'url', 'max:4096'],
            'source_type' => ['required', 'string', Rule::in(['news', 'evidence', 'official_response', 'external'])],
            'news_url' => ['nullable', 'url', 'max:4096'],
            'evidence_id' => ['nullable', 'integer', 'exists:evidences,id'],
            'official_response_id' => ['nullable', 'integer', 'exists:official_responses,id'],
        ]);

        if ((int) $validated['from_entity_id'] === (int) $validated['to_entity_id']) {
            return response()->json([
                'message' => 'Relationship endpoints must be different.',
                'errors' => ['to_entity_id' => ['Relationship endpoints must be different.']],
            ], 422);
        }

        $isHighRisk = $this->isHighRiskRelationship($validated['relationship_type']);
        if ($isHighRisk && ! ($validated['evidence_id'] ?? null) && ! $this->isTrustedSourceUrl($validated['source_url'])) {
            return response()->json([
                'message' => 'High-risk relationships require an existing evidence record or a trusted source URL.',
                'errors' => [
                    'source_url' => ['High-risk relationships require an existing evidence record or a trusted source URL.'],
                ],
            ], 422);
        }

        $newsUrl = null;
        if (($validated['source_type'] === 'news' || ($validated['news_url'] ?? null)) && ($validated['source_url'] ?? null)) {
            $newsUrl = $this->ensureNewsUrl($fingerprints, $validated['news_url'] ?? $validated['source_url']);
            $this->attachNewsItem($event, $newsUrl, $request->user()?->id, [
                'title' => $newsUrl->title_snapshot,
                'summary' => $validated['description'] ?? null,
            ]);
        }

        $before = $relationship->toArray();
        $relationship->forceFill([
            'from_entity_id' => $validated['from_entity_id'],
            'to_entity_id' => $validated['to_entity_id'],
            'news_url_id' => $newsUrl?->id,
            'evidence_id' => $validated['evidence_id'] ?? null,
            'official_response_id' => $validated['official_response_id'] ?? null,
            'relationship_type' => $validated['relationship_type'],
            'description' => $validated['description'] ?? null,
            'source_url' => $validated['source_url'],
            'source_type' => $validated['source_type'],
            'is_high_risk' => $isHighRisk,
        ])->save();

        $event->forceFill([
            'last_activity_at' => now(),
            'controversy_score' => $isHighRisk && ! $before['is_high_risk'] ? $event->controversy_score + 1 : $event->controversy_score,
        ])->save();

        $this->logEdit($event, $request, 'updated', $relationship, $before, $relationship->fresh()->toArray(), 'Updated event relationship.');
        if ($isHighRisk) {
            $this->createRelationshipTask($event, $relationship);
        }

        return response()->json([
            'data' => $relationship->fresh()->load(['fromEntity', 'toEntity', 'newsUrl', 'evidence']),
            'task_created' => $isHighRisk,
        ]);
    }

    public function deleteRelationship(Request $request, NewsEvent $event, EventRelationship $relationship): JsonResponse
    {
        $this->abortUnlessRelationshipBelongsToEvent($event, $relationship);

        $before = $relationship->toArray();
        $relationship->delete();
        $event->forceFill(['last_activity_at' => now()])->save();
        $this->logEdit($event, $request, 'deleted', $relationship, $before, null, 'Deleted event relationship.');

        return response()->json(['message' => 'Relationship deleted.']);
    }

    public function editLogs(NewsEvent $event): JsonResponse
    {
        return response()->json([
            'data' => $event->editLogs()
                ->where('is_public', true)
                ->with('user:id,name,display_name,trust_score,identity_level')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (EventEditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                    'reason' => $log->reason,
                    'changes' => $this->logChangeSummary($log),
                    'created_at' => $log->created_at?->toJSON(),
                    'user' => $log->user ? [
                        'name' => $log->user->publicName(),
                        'trust_score' => round((float) $log->user->trust_score, 2),
                        'identity_level' => $log->user->identity_level,
                    ] : null,
                ]),
        ]);
    }

    private function logChangeSummary(EventEditLog $log): array
    {
        $after = is_array($log->after) ? $log->after : [];
        $before = is_array($log->before) ? $log->before : [];
        $source = $after ?: $before;

        $fields = match ($log->subject_type) {
            'NewsEvent' => ['name', 'summary', 'status', 'is_disputed'],
            'NewsEventItem' => ['item_type', 'title', 'summary', 'source_url'],
            'NewsEventTimelineEntry' => ['title', 'summary', 'occurred_at', 'source_type', 'source_url'],
            'EventEntity' => ['name', 'entity_type', 'description', 'source_url'],
            'EventRelationship' => ['relationship_type', 'description', 'source_type', 'source_url', 'is_high_risk'],
            default => ['name', 'title', 'summary', 'source_url'],
        };

        return collect($fields)
            ->filter(fn (string $field): bool => array_key_exists($field, $source))
            ->map(fn (string $field): array => [
                'field' => $field,
                'before' => $before[$field] ?? null,
                'after' => $after[$field] ?? null,
            ])
            ->values()
            ->all();
    }

    public function rollback(Request $request, NewsEvent $event, EventEditLog $log): JsonResponse
    {
        $user = $request->user();
        if (! $user?->is_admin && (float) $user?->trust_score < 2.0) {
            return response()->json(['message' => 'Only admins or high-trust users can rollback event edits.'], 403);
        }

        if ((int) $log->news_event_id !== (int) $event->id || ! is_array($log->before)) {
            return response()->json(['message' => 'This edit log cannot be rolled back.'], 422);
        }

        $model = $this->modelForLog($log);
        if (! $model) {
            return response()->json(['message' => 'Rollback target not found.'], 404);
        }

        $before = collect($log->before)->except(['id', 'created_at', 'updated_at'])->all();
        $current = $model->toArray();
        $model->forceFill($before)->save();

        $this->logEdit($event, $request, 'rollback', $model, $current, $model->fresh()->toArray(), "Rolled back edit log {$log->id}.");
        $this->recordModerationEvent($event, $request, 'event.rollback', "事件「{$event->name}」回滾一筆社群編輯。", ['edit_log_id' => $log->id]);

        return response()->json(['message' => 'Rollback completed.', 'data' => $model->fresh()]);
    }

    private function showPayload(NewsEvent $event): array
    {
        $event->loadCount(['items', 'timelineEntries', 'entities', 'relationships']);
        $event->load([
            'primaryNewsUrl:id,title_snapshot,normalized_url',
            'creator:id,name,display_name,trust_score',
            'items' => fn ($query) => $query->with(['newsUrl:id,title_snapshot,normalized_url', 'evidence:id,url,preview_url', 'officialResponse:id,response_type,response_text'])->latest()->limit(30),
        ]);

        return [
            'data' => $this->eventPayload($event),
        ];
    }

    private function eventPayload(NewsEvent $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'summary' => $event->summary,
            'status' => $event->status,
            'is_disputed' => $event->is_disputed,
            'controversy_score' => $event->controversy_score,
            'view_count' => $event->view_count,
            'last_viewed_at' => $event->last_viewed_at?->toJSON(),
            'last_activity_at' => $event->last_activity_at?->toJSON(),
            'created_at' => $event->created_at?->toJSON(),
            'primary_news' => $event->primaryNewsUrl,
            'counts' => [
                'items' => $event->items_count ?? null,
                'timeline' => $event->timeline_entries_count ?? null,
                'entities' => $event->entities_count ?? null,
                'relationships' => $event->relationships_count ?? null,
            ],
            'creator' => $event->creator ? [
                'name' => $event->creator->publicName(),
                'trust_score' => round((float) $event->creator->trust_score, 2),
            ] : null,
            'items' => $event->relationLoaded('items') ? $event->items : null,
        ];
    }

    private function ensureNewsUrl(UrlFingerprintService $fingerprints, string $url, ?string $title = null): NewsUrl
    {
        $fingerprint = $fingerprints->fingerprint($url);

        return NewsUrl::query()->firstOrCreate(
            ['hash' => $fingerprint['hash']],
            [
                'original_url' => $fingerprint['original_url'],
                'normalized_url' => $fingerprint['normalized_url'],
                'title_snapshot' => $title,
                'voting_closes_at' => now()->addHours(72),
            ],
        );
    }

    private function attachNewsItem(NewsEvent $event, NewsUrl $newsUrl, ?int $userId, array $attributes = []): NewsEventItem
    {
        return NewsEventItem::query()->firstOrCreate(
            ['news_event_id' => $event->id, 'news_url_id' => $newsUrl->id],
            [
                'created_by' => $userId,
                'item_type' => 'news',
                'title' => $attributes['title'] ?? $newsUrl->title_snapshot,
                'summary' => $attributes['summary'] ?? null,
                'source_url' => $newsUrl->normalized_url,
            ],
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::slug(Str::limit($name, 40, ''));
        $base = $base ?: 'event';
        $slug = $base;
        $counter = 2;

        while (NewsEvent::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function logEdit(NewsEvent $event, Request $request, string $action, Model $subject, ?array $before, ?array $after, ?string $reason = null): void
    {
        EventEditLog::query()->create([
            'news_event_id' => $event->id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'is_public' => true,
        ]);
    }

    private function recordModerationEvent(NewsEvent $event, Request $request, string $type, string $reason, array $metadata = []): void
    {
        ModerationEvent::query()->create([
            'user_id' => $request->user()?->id,
            'event_type' => $type,
            'subject_type' => NewsEvent::class,
            'subject_id' => $event->id,
            'public_reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    private function isHighRiskRelationship(string $type): bool
    {
        $normalized = strtolower(str_replace([' ', '-'], '_', trim($type)));

        return in_array($type, self::HIGH_RISK_RELATIONSHIPS, true)
            || in_array($normalized, self::HIGH_RISK_RELATIONSHIPS, true);
    }

    private function isTrustedSourceUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        return TrustedEvidenceSource::query()
            ->where('is_active', true)
            ->pluck('host')
            ->filter()
            ->contains(fn (string $trustedHost): bool => $host === strtolower($trustedHost) || str_ends_with($host, '.' . strtolower($trustedHost)));
    }

    private function createRelationshipTask(NewsEvent $event, EventRelationship $relationship): void
    {
        CommunityTask::query()->updateOrCreate(
            [
                'type' => 'event_relationship_review',
                'subject_type' => EventRelationship::class,
                'subject_id' => $relationship->id,
            ],
            [
                'subject_key' => "event_relationship:{$relationship->id}",
                'title' => "確認高風險關係：{$event->name}",
                'description' => "社群新增「{$relationship->relationship_type}」關係，需要確認來源是否足夠。",
                'priority' => 80,
                'status' => 'open',
                'action_url' => "/events/{$event->id}?tab=graph",
                'metrics' => [
                    'event_id' => $event->id,
                    'relationship_id' => $relationship->id,
                    'source_url' => $relationship->source_url,
                ],
            ],
        );
    }

    private function abortUnlessEntityBelongsToEvent(NewsEvent $event, EventEntity $entity): void
    {
        abort_unless((int) $entity->news_event_id === (int) $event->id, 404);
    }

    private function abortUnlessRelationshipBelongsToEvent(NewsEvent $event, EventRelationship $relationship): void
    {
        abort_unless((int) $relationship->news_event_id === (int) $event->id, 404);
    }

    private function abortUnlessTimelineBelongsToEvent(NewsEvent $event, NewsEventTimelineEntry $entry): void
    {
        abort_unless((int) $entry->news_event_id === (int) $event->id, 404);
    }

    private function modelForLog(EventEditLog $log): ?Model
    {
        $class = match ($log->subject_type) {
            'NewsEvent' => NewsEvent::class,
            'NewsEventItem' => NewsEventItem::class,
            'NewsEventTimelineEntry' => NewsEventTimelineEntry::class,
            'EventEntity' => EventEntity::class,
            'EventRelationship' => EventRelationship::class,
            default => null,
        };

        return $class ? $class::query()->find($log->subject_id) : null;
    }
}
