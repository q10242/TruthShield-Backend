<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\InspectAbuseSignalsJob;
use App\Models\EvidenceReaction;
use App\Models\EvidenceReport;
use App\Models\Vote;
use App\Services\AccountSignalService;
use App\Services\AuditLogService;
use App\Services\CommunitySignalService;
use App\Services\BotProtectionService;
use App\Services\NewsAggregationService;
use App\Services\NotificationService;
use App\Services\EvidenceSyncService;
use App\Services\TrustScoreService;
use App\Services\UrlFingerprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EvidenceController extends Controller
{
    public function index(
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

        return response()->json(['data' => $newsAggregation->evidenceForFingerprint($fingerprint, $this->locale($request))]);
    }

    private function locale(Request $request): string
    {
        $requested = $request->query('locale') ?: $request->header('Accept-Language', '');

        return str_starts_with(strtolower($requested), 'en') ? 'en' : 'zh-TW';
    }

    public function react(
        Request $request,
        Vote $vote,
        TrustScoreService $trustScores,
        NewsAggregationService $newsAggregation,
        AuditLogService $auditLog,
        EvidenceSyncService $evidenceSync,
        AccountSignalService $accountSignals,
        CommunitySignalService $communitySignals,
        BotProtectionService $botProtection,
    ): JsonResponse
    {
        if ($response = $botProtection->enforce($request, 'evidence.react')) {
            return $response;
        }

        $validated = $request->validate([
            'helpful' => ['required', 'boolean'],
            'credibility' => ['nullable', 'integer', 'min:1', 'max:5'],
            'relevance' => ['nullable', 'integer', 'min:1', 'max:5'],
            'direction' => ['nullable', 'string', 'in:supports,refutes,contextual'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $vote->loadMissing('newsUrl');

        if (! $trustScores->canReactToEvidence($request->user())) {
            return response()->json([
                'message' => 'Trust score is too low to rate evidence.',
                'minimum_trust_score' => $trustScores->evidenceReactionMinTrustScore(),
                'trust_score' => (float) $request->user()->trust_score,
            ], 403);
        }

        $reaction = EvidenceReaction::query()->updateOrCreate(
            [
                'vote_id' => $vote->id,
                'user_id' => $request->user()->id,
            ],
            [
                'helpful' => $validated['helpful'],
                'credibility' => $validated['credibility'] ?? null,
                'relevance' => $validated['relevance'] ?? null,
                'direction' => $validated['direction'] ?? 'contextual',
                'weight_score' => $trustScores->voteWeightFor($request->user()),
            ],
        );
        $auditLog->record($request, 'evidence.reacted', $reaction, [
            'vote_id' => $vote->id,
            'helpful' => $validated['helpful'],
            'credibility' => $validated['credibility'] ?? null,
            'relevance' => $validated['relevance'] ?? null,
            'direction' => $validated['direction'] ?? 'contextual',
        ]);
        $accountSignals->record($request, $request->user(), $vote->newsUrl, 'evidence_reaction');
        $communitySignals->record(
            $request,
            $validated['helpful'] ? 'evidence_helpful' : 'evidence_unhelpful',
            $vote,
            "vote:{$vote->id}",
            $validated['helpful'] ? 'helpful' : 'unhelpful',
            [
                'news_url_id' => $vote->news_url_id,
                'credibility' => $validated['credibility'] ?? null,
                'relevance' => $validated['relevance'] ?? null,
                'direction' => $validated['direction'] ?? 'contextual',
            ],
        );
        InspectAbuseSignalsJob::dispatch($request->user()->id, $vote->newsUrl->id, $vote->id, 'reaction');
        $evidenceSync->syncFromVote($vote->refresh());
        $newsAggregation->forgetStatusCache($vote->newsUrl);

        return response()->json([
            'message' => 'Evidence reaction recorded.',
            'reaction' => $reaction,
        ]);
    }

    public function report(Request $request, Vote $vote, AuditLogService $auditLog, NotificationService $notifications, CommunitySignalService $communitySignals, BotProtectionService $botProtection): JsonResponse
    {
        if ($response = $botProtection->enforce($request, 'evidence.report')) {
            return $response;
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:500'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $report = EvidenceReport::query()->updateOrCreate(
            [
                'vote_id' => $vote->id,
                'user_id' => $request->user()?->id,
            ],
            [
                'reason' => $validated['reason'],
                'note' => $validated['note'] ?? null,
                'status' => 'pending',
            ],
        );

        $auditLog->record($request, 'evidence.reported', $report, [
            'vote_id' => $vote->id,
            'reason' => $validated['reason'],
        ]);
        $communitySignals->record(
            $request,
            'evidence_report',
            $report,
            "vote:{$vote->id}",
            $validated['reason'],
            ['vote_id' => $vote->id],
        );

        $vote->loadMissing(['user', 'newsUrl']);
        if ($vote->user && $vote->user->id !== $request->user()?->id) {
            $notifications->send(
                $vote->user,
                'evidence.reported',
                '你的證據收到檢舉',
                '管理員會檢視這筆證據是否需要處理。',
                $vote->newsUrl?->normalized_url,
                ['vote_id' => $vote->id, 'report_id' => $report->id],
            );
        }

        return response()->json([
            'message' => 'Evidence report received.',
            'report' => $report,
        ], 201);
    }
}
