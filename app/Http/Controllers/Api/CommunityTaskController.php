<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityTask;
use App\Services\BotProtectionService;
use App\Services\CommunityAutomationService;
use App\Services\CommunitySignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommunityTaskController extends Controller
{
    private const USER_PROPOSABLE_TYPES = [
        'controversial_news',
        'fact_check_request',
        'event_creation_request',
        'needs_official_response',
        'evidence_quality_review',
        'timeline_source_review',
        'official_source_request',
        'entity_relationship_review',
        'news_cluster_review',
        'domain_candidate',
        'youtube_channel_candidate',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'in:open,escalated,resolved'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = CommunityTask::query()
            ->when($validated['q'] ?? null, function ($builder, string $q) {
                $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($q)) . '%';

                $builder->where(function ($query) use ($needle): void {
                    $query
                        ->where('title', 'like', $needle)
                        ->orWhere('description', 'like', $needle)
                        ->orWhere('subject_key', 'like', $needle)
                        ->orWhere('type', 'like', $needle);
                });
            })
            ->when($validated['type'] ?? null, fn ($builder, $type) => $builder->where('type', $type))
            ->where('status', $validated['status'] ?? 'open')
            ->when(isset($validated['priority']), fn ($builder) => $builder->where('priority', '>=', $validated['priority']))
            ->orderByDesc('priority')
            ->latest('updated_at');

        $limit = (int) ($validated['limit'] ?? 50);
        $total = (clone $query)->count();

        return response()->json([
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'filters' => [
                    'type' => $validated['type'] ?? null,
                    'status' => $validated['status'] ?? 'open',
                    'priority' => $validated['priority'] ?? null,
                    'q' => $validated['q'] ?? null,
                ],
            ],
            'data' => $query->limit($limit)->get(),
        ]);
    }

    public function show(CommunityTask $task, CommunityAutomationService $automation): JsonResponse
    {
        return response()->json($automation->taskDetail($task));
    }

    public function store(Request $request, CommunityAutomationService $automation, CommunitySignalService $signals, BotProtectionService $botProtection): JsonResponse
    {
        if ($response = $botProtection->enforce($request, 'community.task_proposal')) {
            return $response;
        }

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(self::USER_PROPOSABLE_TYPES)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:1000'],
            'source_url' => ['nullable', 'url', 'max:4096'],
            'note' => ['nullable', 'string', 'max:500'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $fingerprintSource = Str::lower(trim(($validated['source_url'] ?? '') ?: $validated['title']));
        $subjectKey = 'user-proposal:' . $validated['type'] . ':' . sha1($fingerprintSource);
        $existing = CommunityTask::query()
            ->where('type', $validated['type'])
            ->where('subject_key', $subjectKey)
            ->whereIn('status', ['open', 'escalated'])
            ->first();
        $existingMetrics = $existing?->metrics ?? [];
        $metrics = [
            ...$existingMetrics,
            'proposal_count' => (int) (($existingMetrics['proposal_count'] ?? 0) + 1),
            'source_url' => $validated['source_url'] ?? null,
            'proposed_by_user_id' => $request->user()?->id,
        ];

        $task = CommunityTask::query()->updateOrCreate(
            $existing ? ['id' => $existing->id] : ['type' => $validated['type'], 'subject_key' => $subjectKey, 'status' => 'open'],
            [
                'type' => $validated['type'],
                'subject_type' => 'user_proposal',
                'subject_id' => null,
                'subject_key' => $subjectKey,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'priority' => $existing?->priority ?? $this->initialPriorityFor($validated['type']),
                'status' => $existing?->status ?? 'open',
                'action_url' => $validated['type'] === 'event_creation_request' ? '/events' : ($validated['source_url'] ?? null),
                'metrics' => $metrics,
                'generation_snapshot' => [
                    'reason' => 'user_proposed_task',
                    'source_url' => $validated['source_url'] ?? null,
                    'proposal_note' => $validated['note'] ?? null,
                    'generated_at' => now()->toJSON(),
                ],
                'expires_at' => $existing?->expires_at ?? now()->addDays(14),
            ],
        );

        $signalType = $automation->signalTypeForTask($task->type);
        if ($signalType) {
            $signals->record($request, $signalType, $task, $task->subject_key, $this->proposalSignalValue($task->type), [
                'task_id' => $task->id,
                'task_type' => $task->type,
                'note' => $validated['note'] ?? null,
                'source_url' => $validated['source_url'] ?? null,
                'created_from' => 'user_task_proposal',
            ]);
        }

        return response()->json([
            'message' => 'Community task proposal recorded.',
            'task' => $task->fresh(),
            'detail' => $automation->taskDetail($task->fresh()),
        ], $task->wasRecentlyCreated ? 201 : 200);
    }

    public function signal(Request $request, CommunityTask $task, CommunityAutomationService $automation, CommunitySignalService $signals, BotProtectionService $botProtection): JsonResponse
    {
        if ($response = $botProtection->enforce($request, 'community.signal')) {
            return $response;
        }

        $validated = $request->validate([
            'value' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
        ]);

        if (
            in_array($task->type, ['fact_check_request', 'event_creation_request'], true)
            && in_array($validated['value'], ['submit_fact_check', 'submit_event_created'], true)
            && ! trim((string) ($validated['note'] ?? ''))
        ) {
            return response()->json([
                'message' => 'Result note is required.',
                'errors' => [
                    'note' => ['Result note is required.'],
                ],
            ], 422);
        }

        $baseSignalType = $automation->signalTypeForTask($task->type);
        if (! $baseSignalType) {
            return response()->json(['message' => 'This task does not accept direct community signals.'], 422);
        }

        $signalType = str_starts_with($validated['value'], 'reject_')
            ? "{$baseSignalType}_rejection"
            : $baseSignalType;

        $signal = $signals->record($request, $signalType, $task, $task->subject_key, $validated['value'], [
            'task_id' => $task->id,
            'task_type' => $task->type,
            'note' => $validated['note'] ?? null,
        ]);
        $automation->resolveByTaskConsensus($task->refresh());

        return response()->json([
            'message' => 'Community signal recorded.',
            'signal' => $signal,
            'detail' => $automation->taskDetail($task->refresh()),
        ], 201);
    }

    public function stats(CommunityAutomationService $automation): JsonResponse
    {
        return response()->json($automation->stats());
    }

    private function initialPriorityFor(string $type): int
    {
        return match ($type) {
            'fact_check_request' => 60,
            'event_creation_request' => 58,
            'timeline_source_review' => 56,
            'official_source_request' => 55,
            'needs_official_response' => 55,
            'entity_relationship_review' => 52,
            'news_cluster_review' => 48,
            'controversial_news' => 50,
            'evidence_quality_review' => 45,
            default => 40,
        };
    }

    private function proposalSignalValue(string $type): string
    {
        return match ($type) {
            'fact_check_request' => 'request_fact_check',
            'event_creation_request' => 'request_event_creation',
            'needs_official_response' => 'needs_official_response',
            'timeline_source_review' => 'confirm_timeline_source',
            'official_source_request' => 'needs_official_source',
            'entity_relationship_review' => 'confirm_entity_relationship',
            'news_cluster_review' => 'confirm_news_cluster',
            'evidence_quality_review' => 'confirm_evidence_unhelpful',
            'domain_candidate' => 'confirm_news_domain',
            'youtube_channel_candidate' => 'confirm_youtube_channel',
            default => 'needs_more_evidence',
        };
    }
}
