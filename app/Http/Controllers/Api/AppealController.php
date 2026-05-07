<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appeal;
use App\Services\AuditLogService;
use App\Services\ModerationEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->appeals()->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request, AuditLogService $auditLog, ModerationEventService $moderation): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', 'string', 'in:evidence,trust,user_restriction,verified_claimant,official_response'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:120'],
            'statement' => ['required', 'string', 'max:2000'],
        ]);

        $appeal = Appeal::query()->create([
            'user_id' => $request->user()->id,
            ...$validated,
            'status' => 'pending',
        ]);

        $auditLog->record($request, 'appeal.created', $appeal, [
            'subject_type' => $validated['subject_type'],
            'subject_id' => $validated['subject_id'],
        ]);
        $moderation->record($request, 'appeal.created', $appeal, '使用者提出申訴', [
            'subject_type' => $validated['subject_type'],
        ]);

        return response()->json([
            'message' => 'Appeal received.',
            'appeal' => $appeal,
        ], 201);
    }
}
