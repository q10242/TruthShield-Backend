<?php

namespace App\Http\Controllers\Api;

use App\Models\NewsUrl;
use App\Models\Tag;
use App\Models\Vote;
use App\Http\Controllers\Controller;
use App\Jobs\InspectAbuseSignalsJob;
use App\Services\AccountSignalService;
use App\Services\AuditLogService;
use App\Services\BotProtectionService;
use App\Services\EvidenceUrlService;
use App\Services\EvidenceSyncService;
use App\Services\MediaOutletService;
use App\Services\NewsAggregationService;
use App\Services\TrustScoreService;
use App\Services\UrlFingerprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class VoteController extends Controller
{
    public function store(
        Request $request,
        UrlFingerprintService $fingerprints,
        TrustScoreService $trustScores,
        NewsAggregationService $newsAggregation,
        MediaOutletService $mediaOutlets,
        AuditLogService $auditLog,
        EvidenceUrlService $evidenceUrls,
        EvidenceSyncService $evidenceSync,
        AccountSignalService $accountSignals,
        BotProtectionService $botProtection,
    ): JsonResponse {
        if ($response = $botProtection->enforce($request, 'vote.create')) {
            return $response;
        }

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
            'tag_id' => ['required', 'integer', 'exists:tags,id'],
            'secondary_tag_ids' => ['nullable', 'array', 'max:4'],
            'secondary_tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'evidence_url' => ['nullable', 'url', 'max:2048'],
            'evidence_note' => ['nullable', 'string', 'max:320'],
            'title_snapshot' => ['nullable', 'string', 'max:255'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $tag = Tag::query()->findOrFail($validated['tag_id']);
        $secondaryTagIds = collect($validated['secondary_tag_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $validated['tag_id'])
            ->unique()
            ->values()
            ->all();
        $evidenceUrl = $validated['evidence_url'] ?? null;
        $evidence = null;

        if ($tag->requiresEvidenceUrl() && ! $evidenceUrl) {
            return response()->json([
                'message' => 'Evidence URL is required for this tag.',
                'errors' => [
                    'evidence_url' => ['Evidence URL is required for this tag.'],
                ],
            ], 422);
        }

        if ($tag->requiresEvidenceNote() && ! trim((string) ($validated['evidence_note'] ?? ''))) {
            return response()->json([
                'message' => 'Evidence note is required for this tag.',
                'errors' => [
                    'evidence_note' => ['Evidence note is required for this tag.'],
                ],
            ], 422);
        }

        try {
            $evidence = $evidenceUrls->inspect($evidenceUrl);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'evidence_url' => [$exception->getMessage()],
                ],
            ], 422);
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
                'title_snapshot' => $validated['title_snapshot'] ?? null,
                'voting_closes_at' => now()->addHours(72),
            ],
        );

        $newsAggregation->forgetMissingStatusCache($fingerprint['hash']);
        $newsAggregation->ensureVotingWindow($newsUrl);
        $mediaOutlets->attachOutlet($newsUrl);

        if (! $newsAggregation->isOpen($newsUrl)) {
            return response()->json([
                'error_code' => 'voting_window_closed',
                'message' => 'Voting window has closed for this news URL.',
                'status' => $newsAggregation->statusForFingerprint($fingerprint),
            ], 409);
        }

        if (! $newsUrl->title_snapshot && ! empty($validated['title_snapshot'])) {
            $newsUrl->forceFill(['title_snapshot' => $validated['title_snapshot']])->save();
        }

        $minimumReadSeconds = (int) config('truthshield.min_read_seconds_before_vote', 15);
        $secondsRead = (int) $request->user()
            ->readSessions()
            ->where('news_url_id', $newsUrl->id)
            ->value('seconds_read');

        if ($minimumReadSeconds > 0 && $secondsRead < $minimumReadSeconds) {
            return response()->json([
                'error_code' => 'read_required',
                'message' => 'Please read the article before voting.',
                'minimum_read_seconds' => $minimumReadSeconds,
                'seconds_read' => $secondsRead,
            ], 428);
        }

        $existingVote = Vote::query()
            ->where('user_id', $request->user()->id)
            ->where('news_url_id', $newsUrl->id)
            ->first();

        // Toggle: same tag clicked again → cancel the vote
        if ($existingVote && $existingVote->tag_id === (int) $validated['tag_id']) {
            $existingVote->delete();
            $newsAggregation->forgetStatusCache($newsUrl);
            $cache = Cache::store(config('truthshield.status_cache_store'));
            foreach (['leaderboard:media:v1', 'transparency:summary:v1', 'system:health:metrics:v1'] as $key) {
                $cache->forget($key);
            }
            $auditLog->record($request, 'vote.cancelled', $existingVote, [
                'news_url_id' => $newsUrl->id,
                'tag_id' => $validated['tag_id'],
            ]);
            return response()->json(['message' => 'Vote cancelled.', 'vote' => null], 200);
        }

        $vote = Vote::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'news_url_id' => $newsUrl->id,
            ],
            [
                'tag_id' => $validated['tag_id'],
                'secondary_tag_ids' => $secondaryTagIds,
                'evidence_url' => $evidenceUrl,
                'evidence_type' => $evidence['type'],
                'evidence_host' => $evidence['host'],
                'evidence_safety' => $evidence['safety'],
                'evidence_note' => $validated['evidence_note'] ?? null,
                'weight_score' => $trustScores->voteWeightFor($request->user()),
            ],
        );

        $newsAggregation->forgetStatusCache($newsUrl);
        $cache = Cache::store(config('truthshield.status_cache_store'));
        foreach (['leaderboard:media:v1', 'transparency:summary:v1', 'system:health:metrics:v1'] as $key) {
            $cache->forget($key);
        }
        $evidenceSync->syncFromVote($vote, $evidence);
        $auditLog->record($request, 'vote.upserted', $vote, [
            'news_url_id' => $newsUrl->id,
            'tag_id' => $validated['tag_id'],
        ]);
        $accountSignals->record($request, $request->user(), $newsUrl, 'vote');
        InspectAbuseSignalsJob::dispatch($request->user()->id, $newsUrl->id, $vote->id, 'vote');

        return response()->json([
            'message' => 'Vote recorded.',
            'vote' => $vote->load(['tag:id,name,slug,color', 'newsUrl:id,hash,normalized_url,title_snapshot']),
        ], 201);
    }
}
