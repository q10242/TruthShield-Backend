<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityTask;
use App\Services\BotProtectionService;
use App\Services\CommunityAutomationService;
use App\Services\CommunitySignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'in:open,escalated,resolved'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = CommunityTask::query()
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
                ],
            ],
            'data' => $query->limit($limit)->get(),
        ]);
    }

    public function show(CommunityTask $task, CommunityAutomationService $automation): JsonResponse
    {
        return response()->json($automation->taskDetail($task));
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
}
