<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlgorithmVersion;
use App\Models\SystemSetting;
use App\Services\AlgorithmVersionService;
use Illuminate\Http\JsonResponse;

class AlgorithmController extends Controller
{
    public function show(AlgorithmVersionService $versions): JsonResponse
    {
        $current = $versions->ensureCurrent();
        $summary = SystemSetting::query()->where('key', 'algorithm_summary')->value('value') ?? [];

        return response()->json([
            'summary' => $summary,
            'version' => $current->version,
            'principles' => [
                'TruthShield does not remove news articles.',
                'Votes are weighted by user trust score, identity multiplier, and abuse multiplier.',
                'Evidence usefulness can affect future trust score.',
                'Results are finalized after the voting window closes.',
            ],
            'config' => [
                'evidence_reaction_min_trust_score' => config('truthshield.evidence_reaction_min_trust_score'),
                'low_trust_vote_cap' => config('truthshield.low_trust_vote_cap'),
                'algorithm_version' => config('truthshield.algorithm_version', 'truthshield-v1'),
            ],
            'history' => AlgorithmVersion::query()->latest('activated_at')->limit(10)->get(),
        ]);
    }
}
