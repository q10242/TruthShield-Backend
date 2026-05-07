<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtensionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    public function storeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.domain' => ['required', 'string', 'max:255'],
            'events.*.event_type' => ['required', 'string', 'max:80'],
            'events.*.extension_version' => ['nullable', 'string', 'max:40'],
            'events.*.success' => ['nullable', 'boolean'],
            'events.*.metadata' => ['nullable', 'array'],
        ]);

        $now = Carbon::now();
        $rows = collect($validated['events'])
            ->map(fn (array $event): array => [
                'domain' => strtolower($event['domain']),
                'event_type' => $event['event_type'],
                'extension_version' => $event['extension_version'] ?? null,
                'success' => (bool) ($event['success'] ?? true),
                'metadata' => isset($event['metadata']) ? json_encode($event['metadata']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        ExtensionEvent::query()->insert($rows);

        return response()->json([
            'message' => 'Extension events recorded.',
            'accepted' => count($rows),
        ], 202);
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
