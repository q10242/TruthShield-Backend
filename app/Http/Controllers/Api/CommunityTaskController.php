<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityTask;
use App\Services\CommunityAutomationService;
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

    public function stats(CommunityAutomationService $automation): JsonResponse
    {
        return response()->json($automation->stats());
    }
}
