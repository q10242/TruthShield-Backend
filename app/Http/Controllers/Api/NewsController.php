<?php

namespace App\Http\Controllers\Api;

use App\Services\NewsAggregationService;
use App\Services\UrlFingerprintService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class NewsController extends Controller
{
    public function status(
        Request $request,
        UrlFingerprintService $fingerprints,
        NewsAggregationService $newsAggregation,
    ): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
        ]);

        try {
            $fingerprint = $fingerprints->fingerprint($validated['url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()
            ->json($newsAggregation->statusForFingerprint($fingerprint))
            ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=120');
    }
}
