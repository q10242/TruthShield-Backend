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
                    'description' => 'Reading gates, weighted votes, evidence requirements, rate limits, bot challenges, and reviewable abuse events reduce manipulation without using deletion as the first response.',
                ],
                [
                    'key' => 'article_snapshots',
                    'title' => 'Article snapshots without full-text storage',
                    'description' => 'TruthShield stores metadata, availability, and change history so deleted or edited articles remain auditable without mirroring copyrighted full text.',
                ],
            ],
            'anti_abuse_transparency' => [
                'public_rules' => [
                    [
                        'key' => 'weighted_votes',
                        'title' => 'Weighted votes',
                        'description' => 'Results use trust_score * identity_multiplier * abuse_multiplier instead of raw vote counts.',
                    ],
                    [
                        'key' => 'reading_gate',
                        'title' => 'Reading gate',
                        'description' => 'Votes require a minimum reading signal before they can be accepted.',
                    ],
                    [
                        'key' => 'evidence_required',
                        'title' => 'Evidence required for negative labels',
                        'description' => 'Negative labels require a public evidence URL and a short explanation.',
                    ],
                    [
                        'key' => 'one_entry',
                        'title' => 'One entry per article per user',
                        'description' => 'A user can update one vote and evidence note for the same article before the voting window closes.',
                    ],
                    [
                        'key' => 'low_trust_limits',
                        'title' => 'Low-trust accounts can still participate',
                        'description' => 'Low-trust or new accounts can submit votes and evidence, but their weight and reaction privileges may be limited until trust is earned.',
                    ],
                    [
                        'key' => 'review_first_for_bursts',
                        'title' => 'Burst behavior is reviewed before heavy restriction',
                        'description' => 'Repeated evidence URLs and short-window same-label vote bursts create abuse events for review instead of automatically applying severe penalties.',
                    ],
                ],
                'protected_details' => [
                    'Exact bot risk scoring weights.',
                    'Exact challenge thresholds.',
                    'Blocked user-agent patterns.',
                    'Extension nonce validation details.',
                    'Some short-window burst thresholds that would allow adversaries to tune around the system.',
                ],
                'user_protections' => [
                    'Suspicious behavior is generally down-weighted or sent to review before high-impact penalties.',
                    'Users can still read results and submit evidence even when their weight is low.',
                    'Risk restrictions, evidence hiding, trust adjustments, and appeals leave governance records.',
                    'Users can appeal high-impact moderation or weight decisions.',
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
