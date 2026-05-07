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
            'rules' => [
                [
                    'key' => 'voting_window',
                    'title' => '72 hour finalization window',
                    'description' => 'A URL stays open for weighted voting and evidence reactions for 72 hours after first collection. After closing, the result is frozen as a snapshot.',
                ],
                [
                    'key' => 'vote_weight',
                    'title' => 'Weighted voting',
                    'description' => 'Vote weight is calculated from the user trust score plus identity and abuse multipliers. Low-trust or restricted accounts can be capped.',
                ],
                [
                    'key' => 'evidence_quality',
                    'title' => 'Evidence usefulness',
                    'description' => 'Helpful and unhelpful evidence reactions are weighted. Evidence quality can affect future trust score settlement.',
                ],
                [
                    'key' => 'anti_abuse',
                    'title' => 'Anti-manipulation safeguards',
                    'description' => 'Coordinated voting, repeated evidence URLs, low reading time, and fresh-account bursts can lower future abuse multipliers.',
                ],
                [
                    'key' => 'article_snapshots',
                    'title' => 'Article snapshots without full-text storage',
                    'description' => 'TruthShield stores metadata, availability, and change history so deleted or edited articles remain auditable without mirroring copyrighted full text.',
                ],
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
