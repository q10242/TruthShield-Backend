<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsUrl;
use App\Models\ReadSession;
use App\Services\MediaOutletService;
use App\Services\NewsAggregationService;
use App\Services\UrlFingerprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReadSessionController extends Controller
{
    public function store(
        Request $request,
        UrlFingerprintService $fingerprints,
        NewsAggregationService $newsAggregation,
        MediaOutletService $mediaOutlets,
    ): JsonResponse {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
            'seconds_read' => ['required', 'integer', 'min:0', 'max:86400'],
            'title_snapshot' => ['nullable', 'string', 'max:255'],
            'visible' => ['nullable', 'boolean'],
        ]);

        try {
            $fingerprint = $fingerprints->fingerprint($validated['url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $newsUrl = NewsUrl::query()->firstOrCreate(
            ['hash' => $fingerprint['hash']],
            [
                'original_url' => $fingerprint['original_url'],
                'normalized_url' => $fingerprint['normalized_url'],
                'title_snapshot' => $validated['title_snapshot'] ?? null,
                'voting_closes_at' => now()->addHours(72),
            ],
        );

        $newsAggregation->ensureVotingWindow($newsUrl);
        $mediaOutlets->attachOutlet($newsUrl);

        if (! $newsUrl->title_snapshot && ! empty($validated['title_snapshot'])) {
            $newsUrl->forceFill(['title_snapshot' => $validated['title_snapshot']])->save();
        }

        $existing = ReadSession::query()
            ->where('user_id', $request->user()->id)
            ->where('news_url_id', $newsUrl->id)
            ->first();

        $session = ReadSession::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'news_url_id' => $newsUrl->id,
            ],
            [
                'seconds_read' => max(
                    (int) $validated['seconds_read'],
                    (int) ($existing?->seconds_read ?? 0),
                ),
                'first_seen_at' => $existing?->first_seen_at ?: now(),
                'last_seen_at' => now(),
                'metadata' => [
                    'visible' => (bool) ($validated['visible'] ?? true),
                    'user_agent' => substr((string) $request->userAgent(), 0, 240),
                ],
            ],
        );

        $minimum = (int) config('truthshield.min_read_seconds_before_vote', 15);

        return response()->json([
            'read_session' => $session,
            'minimum_seconds' => $minimum,
            'can_vote' => $session->seconds_read >= $minimum,
        ]);
    }
}
