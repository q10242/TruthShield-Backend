<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsUrl;
use App\Services\NewsAggregationService;
use Illuminate\Http\JsonResponse;

class NewsDetailController extends Controller
{
    public function show(NewsUrl $newsUrl, NewsAggregationService $aggregation): JsonResponse
    {
        $fingerprint = [
            'hash' => $newsUrl->hash,
            'normalized_url' => $newsUrl->normalized_url,
        ];

        return response()->json([
            'news' => $newsUrl->load('mediaOutlet:id,name,slug'),
            'status' => $aggregation->statusForFingerprint($fingerprint),
            'evidence' => $aggregation->evidenceForFingerprint($fingerprint),
        ]);
    }
}
