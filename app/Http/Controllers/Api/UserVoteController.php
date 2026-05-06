<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UrlFingerprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class UserVoteController extends Controller
{
    public function show(Request $request, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate(['url' => ['required', 'url', 'max:4096']]);

        try {
            $fingerprint = $fingerprints->fingerprint($validated['url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $vote = $request->user()
            ->votes()
            ->whereHas('newsUrl', fn ($query) => $query->where('hash', $fingerprint['hash']))
            ->with(['tag:id,name,slug,color,severity,requires_evidence', 'newsUrl:id,hash,normalized_url,voting_closes_at,finalized_at'])
            ->first();

        return response()->json(['vote' => $vote]);
    }
}
