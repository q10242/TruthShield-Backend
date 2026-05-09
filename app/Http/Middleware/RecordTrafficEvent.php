<?php

namespace App\Http\Middleware;

use App\Services\TrafficAnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordTrafficEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('truthshield_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('OPTIONS')) {
            return;
        }

        if (! (bool) config('truthshield_traffic.record_api_requests', true)) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');
        if (! str_starts_with($path, '/api/')) {
            return;
        }

        if (str_starts_with($path, '/api/traffic/events')) {
            return;
        }

        $startedAt = (float) $request->attributes->get('truthshield_started_at', microtime(true));
        $durationMs = max(0, (int) round((microtime(true) - $startedAt) * 1000));

        app(TrafficAnalyticsService::class)->recordFromRequest($request, [
            'event_type' => 'api_request',
            'source' => 'api',
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'success' => $response->getStatusCode() < 400,
            'cache_status' => $response->headers->get('X-TruthShield-Cache'),
        ]);
    }
}
