<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsChangeReport;
use App\Models\NewsUrl;
use App\Services\MediaOutletService;
use App\Services\NewsAggregationService;
use App\Services\UrlFingerprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class NewsChangeReportController extends Controller
{
    public function store(
        Request $request,
        UrlFingerprintService $fingerprints,
        NewsAggregationService $aggregation,
        MediaOutletService $mediaOutlets,
    ): JsonResponse {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
            'report_type' => ['required', 'in:deleted,title_changed,content_changed,paywalled,redirected,archive_needed,other'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
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
                'title_snapshot' => $validated['page_title'] ?? null,
                'voting_closes_at' => now()->addHours(72),
            ],
        );

        $aggregation->ensureVotingWindow($newsUrl);
        $mediaOutlets->attachOutlet($newsUrl);

        $report = NewsChangeReport::query()->create([
            'news_url_id' => $newsUrl->id,
            'user_id' => $request->user()?->id,
            'report_type' => $validated['report_type'],
            'url' => $validated['url'],
            'page_title' => $validated['page_title'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => 'pending',
        ]);

        $aggregation->forgetStatusCache($newsUrl);

        return response()->json([
            'message' => 'report_received',
            'report' => $report,
        ], 201);
    }
}
