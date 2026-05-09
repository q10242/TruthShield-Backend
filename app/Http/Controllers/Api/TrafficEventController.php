<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrafficAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrafficEventController extends Controller
{
    public function store(Request $request, TrafficAnalyticsService $traffic): JsonResponse
    {
        $validated = $this->validateEvent($request);
        $event = $traffic->record($this->payload($request, $validated));

        return response()->json([
            'accepted' => (bool) $event,
        ], 202);
    }

    public function storeBatch(Request $request, TrafficAnalyticsService $traffic): JsonResponse
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:25'],
            'events.*.event_type' => ['required', 'string', 'max:80'],
            'events.*.source' => ['nullable', 'string', 'max:40'],
            'events.*.feature' => ['nullable', 'string', 'max:80'],
            'events.*.path' => ['nullable', 'string', 'max:255'],
            'events.*.domain' => ['nullable', 'string', 'max:255'],
            'events.*.url_hash' => ['nullable', 'string', 'max:128'],
            'events.*.success' => ['nullable', 'boolean'],
            'events.*.cache_status' => ['nullable', 'string', 'max:24'],
            'events.*.locale' => ['nullable', 'string', 'max:16'],
            'events.*.metadata' => ['nullable', 'array'],
        ]);

        $accepted = 0;
        foreach ($validated['events'] as $event) {
            if ($traffic->record($this->payload($request, $event))) {
                $accepted++;
            }
        }

        return response()->json(['accepted' => $accepted], 202);
    }

    public function summary(TrafficAnalyticsService $traffic): JsonResponse
    {
        return response()->json($traffic->publicSummary());
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'event_type' => ['required', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:40'],
            'feature' => ['nullable', 'string', 'max:80'],
            'path' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'url_hash' => ['nullable', 'string', 'max:128'],
            'success' => ['nullable', 'boolean'],
            'cache_status' => ['nullable', 'string', 'max:24'],
            'locale' => ['nullable', 'string', 'max:16'],
            'metadata' => ['nullable', 'array'],
        ]);
    }

    private function payload(Request $request, array $event): array
    {
        return [
            ...$event,
            'source' => $event['source'] ?? 'web',
            'session_hash' => app(TrafficAnalyticsService::class)->sessionHash($request),
            'user_id' => $request->user()?->id,
            'method' => null,
            'status_code' => null,
            'duration_ms' => null,
            'sample_rate' => 1.0,
            'metadata' => [
                ...($event['metadata'] ?? []),
                'client' => $request->header('X-TruthShield-Client', 'web'),
            ],
        ];
    }
}
