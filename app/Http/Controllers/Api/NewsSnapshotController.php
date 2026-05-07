<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsUrl;
use App\Services\MediaOutletService;
use App\Services\NewsAggregationService;
use App\Services\NewsSnapshotService;
use App\Services\UrlFingerprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class NewsSnapshotController extends Controller
{
    public function store(
        Request $request,
        UrlFingerprintService $fingerprints,
        NewsAggregationService $aggregation,
        NewsSnapshotService $snapshots,
        MediaOutletService $mediaOutlets,
    ): JsonResponse {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
            'title_snapshot' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:4096'],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'content_hash' => ['nullable', 'string', 'max:64'],
            'availability_status' => ['nullable', 'in:available,deleted_or_unavailable,redirected,paywalled,unknown'],
            'archive_url' => ['nullable', 'url', 'max:2048'],
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
                'canonical_url' => $validated['canonical_url'] ?? null,
                'voting_closes_at' => now()->addHours(72),
            ],
        );

        $aggregation->ensureVotingWindow($newsUrl);
        $mediaOutlets->attachOutlet($newsUrl);

        $snapshot = $snapshots->capture($newsUrl, [
            ...$validated,
            'source' => 'api',
            'user_agent' => substr((string) $request->userAgent(), 0, 240),
        ]);

        $aggregation->forgetStatusCache($newsUrl);

        return response()->json([
            'news_url_id' => $newsUrl->id,
            'snapshot' => $snapshot,
            'status' => $aggregation->statusForFingerprint($fingerprint),
        ], 201);
    }
}
