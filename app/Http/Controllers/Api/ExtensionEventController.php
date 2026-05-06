<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtensionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtensionEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:80'],
            'extension_version' => ['nullable', 'string', 'max:40'],
            'success' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $event = ExtensionEvent::query()->create([
            ...$validated,
            'domain' => strtolower($validated['domain']),
            'success' => (bool) ($validated['success'] ?? true),
        ]);

        return response()->json(['event' => $event], 201);
    }

    public function coverage(): JsonResponse
    {
        $rows = ExtensionEvent::query()
            ->selectRaw('domain, event_type, count(*) as total, sum(case when success then 1 else 0 end) as successes')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('domain', 'event_type')
            ->orderBy('domain')
            ->get()
            ->groupBy('domain')
            ->map(fn ($events, $domain) => [
                'domain' => $domain,
                'events' => $events->map(fn ($event) => [
                    'event_type' => $event->event_type,
                    'total' => (int) $event->total,
                    'successes' => (int) $event->successes,
                    'success_rate' => (int) $event->total > 0 ? round(((int) $event->successes / (int) $event->total) * 100, 2) : 0,
                ])->values(),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }
}
