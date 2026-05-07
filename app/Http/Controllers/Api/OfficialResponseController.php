<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsUrl;
use App\Models\OfficialResponse;
use App\Models\OfficialResponseReaction;
use App\Models\VerifiedClaimant;
use App\Services\ModerationEventService;
use App\Services\NewsAggregationService;
use App\Services\TrustScoreService;
use App\Services\UrlFingerprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OfficialResponseController extends Controller
{
    public function index(Request $request, UrlFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
        ]);

        try {
            $fingerprint = $fingerprints->fingerprint($validated['url']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $newsUrl = NewsUrl::query()->where('hash', $fingerprint['hash'])->first();
        if (! $newsUrl) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $this->publicResponses($newsUrl)]);
    }

    public function storeClaimant(Request $request, ModerationEventService $moderation): JsonResponse
    {
        $validated = $request->validate([
            'claim_type' => ['required', 'string', 'in:author,media,subject,organization'],
            'domain' => ['nullable', 'string', 'max:255'],
            'news_url_id' => ['nullable', 'integer', 'exists:news_urls,id'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'proof_url' => ['nullable', 'url', 'max:2048'],
            'statement' => ['required', 'string', 'max:1000'],
        ]);

        $claimant = VerifiedClaimant::query()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'domain' => ! empty($validated['domain']) ? strtolower($validated['domain']) : null,
            'status' => 'pending',
        ]);

        $moderation->record($request, 'claimant.submitted', $claimant, '身份驗證申請已送出', [
            'claim_type' => $claimant->claim_type,
            'domain' => $claimant->domain,
        ]);

        return response()->json([
            'message' => 'Claimant verification request submitted.',
            'claimant' => $claimant,
        ], 201);
    }

    public function storeResponse(
        Request $request,
        UrlFingerprintService $fingerprints,
        NewsAggregationService $newsAggregation,
        ModerationEventService $moderation,
    ): JsonResponse {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
            'verified_claimant_id' => ['required', 'integer', 'exists:verified_claimants,id'],
            'response_type' => ['required', 'string', 'in:author_clarification,media_statement,subject_clarification,organization_statement,right_of_reply'],
            'response_text' => ['required', 'string', 'max:3000'],
            'evidence_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $claimant = VerifiedClaimant::query()
            ->where('id', $validated['verified_claimant_id'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'approved')
            ->first();

        if (! $claimant) {
            return response()->json(['message' => 'Approved claimant verification is required.'], 403);
        }

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
                'voting_closes_at' => now()->addHours(72),
            ],
        );
        $newsAggregation->ensureVotingWindow($newsUrl);

        $response = OfficialResponse::query()->create([
            'news_url_id' => $newsUrl->id,
            'user_id' => $request->user()->id,
            'verified_claimant_id' => $claimant->id,
            'response_type' => $validated['response_type'],
            'response_text' => $validated['response_text'],
            'evidence_url' => $validated['evidence_url'] ?? null,
            'status' => 'pending',
        ]);

        $moderation->record($request, 'official_response.submitted', $response, '官方澄清已送出待審', [
            'news_url_id' => $newsUrl->id,
            'response_type' => $response->response_type,
        ]);

        return response()->json([
            'message' => 'Official response submitted for review.',
            'official_response' => $response,
        ], 201);
    }

    public function react(Request $request, OfficialResponse $officialResponse, TrustScoreService $trustScores): JsonResponse
    {
        $validated = $request->validate([
            'helpful' => ['required', 'boolean'],
        ]);

        if ($officialResponse->status !== 'published') {
            return response()->json(['message' => 'Official response is not published.'], 409);
        }

        if (! $trustScores->canReactToEvidence($request->user())) {
            return response()->json([
                'message' => 'Trust score is too low to rate official responses.',
                'minimum_trust_score' => $trustScores->evidenceReactionMinTrustScore(),
            ], 403);
        }

        $reaction = OfficialResponseReaction::query()->updateOrCreate(
            [
                'official_response_id' => $officialResponse->id,
                'user_id' => $request->user()->id,
            ],
            [
                'helpful' => $validated['helpful'],
                'weight_score' => $trustScores->voteWeightFor($request->user()),
            ],
        );

        $officialResponse->forceFill([
            'helpful_weight' => $officialResponse->reactions()->where('helpful', true)->sum('weight_score'),
            'unhelpful_weight' => $officialResponse->reactions()->where('helpful', false)->sum('weight_score'),
        ])->save();

        return response()->json([
            'message' => 'Official response reaction recorded.',
            'reaction' => $reaction,
            'official_response' => $officialResponse->fresh(),
        ]);
    }

    public function publicResponses(NewsUrl $newsUrl)
    {
        return OfficialResponse::query()
            ->with(['user:id,name,display_name,is_real_name_public,public_identity_label', 'verifiedClaimant:id,claim_type,domain,organization_name'])
            ->where('news_url_id', $newsUrl->id)
            ->where('status', 'published')
            ->orderByRaw('(helpful_weight - unhelpful_weight) desc')
            ->latest('published_at')
            ->get()
            ->map(fn (OfficialResponse $response): array => [
                'id' => $response->id,
                'response_type' => $response->response_type,
                'response_text' => $response->response_text,
                'evidence_url' => $response->evidence_url,
                'helpful_weight' => $response->helpful_weight,
                'unhelpful_weight' => $response->unhelpful_weight,
                'published_at' => $response->published_at?->toJSON(),
                'author' => [
                    'display_name' => $response->user?->publicName(),
                    'identity_label' => $response->user?->public_identity_label,
                ],
                'claimant' => [
                    'claim_type' => $response->verifiedClaimant?->claim_type,
                    'domain' => $response->verifiedClaimant?->domain,
                    'organization_name' => $response->verifiedClaimant?->organization_name,
                ],
            ]);
    }
}
