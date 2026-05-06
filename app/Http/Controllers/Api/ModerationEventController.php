<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModerationEvent;
use Illuminate\Http\JsonResponse;

class ModerationEventController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ModerationEvent::query()
                ->latest()
                ->limit(100)
                ->get(['id', 'event_type', 'subject_type', 'public_reason', 'created_at']),
        ]);
    }
}
