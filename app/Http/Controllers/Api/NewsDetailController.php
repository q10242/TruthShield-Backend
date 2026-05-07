<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsUrl;
use App\Http\Controllers\Api\OfficialResponseController;
use App\Services\NewsAggregationService;
use Illuminate\Http\JsonResponse;

class NewsDetailController extends Controller
{
    public function show(NewsUrl $newsUrl, NewsAggregationService $aggregation, OfficialResponseController $officialResponses): JsonResponse
    {
        $fingerprint = [
            'hash' => $newsUrl->hash,
            'normalized_url' => $newsUrl->normalized_url,
        ];

        return response()->json([
            'news' => $newsUrl->load(['mediaOutlet:id,name,slug', 'snapshots' => fn ($query) => $query->latest('captured_at')->limit(8)]),
            'status' => $aggregation->statusForFingerprint($fingerprint),
            'evidence' => $aggregation->evidenceForFingerprint($fingerprint),
            'official_responses' => $officialResponses->publicResponses($newsUrl),
            'vote_history' => $newsUrl->votes()
                ->with(['tag:id,name,slug,color,severity', 'user:id,name,display_name,is_real_name_public,public_identity_label'])
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->map(fn ($vote) => [
                    'id' => $vote->id,
                    'tag' => $vote->tag,
                    'secondary_tag_ids' => $vote->secondary_tag_ids ?: [],
                    'weight_score' => round((float) $vote->weight_score, 4),
                    'has_evidence' => filled($vote->evidence_url),
                    'evidence_note' => $vote->hidden ? null : $vote->evidence_note,
                    'moderation_status' => $vote->moderation_status,
                    'updated_at' => $vote->updated_at?->toJSON(),
                    'author' => [
                        'display_name' => $vote->user?->publicName(),
                        'identity_label' => $vote->user?->public_identity_label,
                    ],
                ]),
        ]);
    }
}
