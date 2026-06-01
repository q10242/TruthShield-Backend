<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEventSystemTrust
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $minimum = (float) config('truthshield.event_system_min_trust_score', 1.0);
        $score = (float) ($user?->trust_score ?? 1.0);

        if (! $user?->is_admin && $score < $minimum) {
            return response()->json([
                'message' => 'Your trust score is too low to edit the event system.',
                'minimum_trust_score' => $minimum,
                'trust_score' => round($score, 2),
            ], 403);
        }

        return $next($request);
    }
}
