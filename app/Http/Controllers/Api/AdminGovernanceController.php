<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbuseEvent;
use App\Models\Appeal;
use App\Models\Evidence;
use App\Models\EvidenceReport;
use App\Models\TrustedEvidenceSource;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ModerationEventService;
use App\Services\NotificationService;
use App\Services\TrustScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGovernanceController extends Controller
{
    public function hideEvidence(Request $request, Evidence $evidence, AuditLogService $auditLog, ModerationEventService $moderation, NotificationService $notifications): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $evidence->loadMissing(['vote', 'user', 'newsUrl']);
        $evidence->forceFill([
            'hidden' => true,
            'moderation_status' => 'hidden',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();
        $evidence->vote?->forceFill(['hidden' => true, 'moderation_status' => 'hidden'])->save();

        $auditLog->record($request, 'admin.evidence.hidden', $evidence, ['reason' => $validated['reason']]);
        $moderation->record($request, 'evidence.hidden', $evidence, $validated['reason']);
        if ($evidence->user) {
            $notifications->send($evidence->user, 'evidence.hidden', '你的證據已被隱藏', $validated['reason'], $evidence->newsUrl?->normalized_url, ['evidence_id' => $evidence->id]);
        }

        return response()->json(['evidence' => $evidence->refresh()]);
    }

    public function restoreEvidence(Request $request, Evidence $evidence, AuditLogService $auditLog, ModerationEventService $moderation, NotificationService $notifications): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $evidence->loadMissing(['vote', 'user', 'newsUrl']);
        $evidence->forceFill([
            'hidden' => false,
            'moderation_status' => 'visible',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();
        $evidence->vote?->forceFill(['hidden' => false, 'moderation_status' => 'visible'])->save();

        $auditLog->record($request, 'admin.evidence.restored', $evidence, ['reason' => $validated['reason']]);
        $moderation->record($request, 'evidence.restored', $evidence, $validated['reason']);
        if ($evidence->user) {
            $notifications->send($evidence->user, 'evidence.restored', '你的證據已恢復顯示', $validated['reason'], $evidence->newsUrl?->normalized_url, ['evidence_id' => $evidence->id]);
        }

        return response()->json(['evidence' => $evidence->refresh()]);
    }

    public function reviewEvidenceReport(Request $request, EvidenceReport $report, AuditLogService $auditLog, ModerationEventService $moderation): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,resolved'],
            'review_note' => ['required', 'string', 'max:500'],
        ]);

        $report->forceFill([
            ...$validated,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $auditLog->record($request, 'admin.evidence_report.reviewed', $report, $validated);
        $moderation->record($request, 'evidence_report.reviewed', $report, $validated['review_note'], ['status' => $validated['status']]);

        return response()->json(['report' => $report->refresh()]);
    }

    public function reviewAbuseEvent(Request $request, AbuseEvent $event, AuditLogService $auditLog, ModerationEventService $moderation, NotificationService $notifications): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'action_taken' => ['required', 'string', 'in:none,watch_user,limit_user,suspend_weight'],
            'review_note' => ['required', 'string', 'max:500'],
        ]);

        $event->forceFill([
            'reviewed' => true,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'],
            'action_taken' => $validated['action_taken'],
        ])->save();

        $target = $event->user;
        if ($target && $validated['action_taken'] !== 'none') {
            $this->applyRiskAction($target, $validated['action_taken']);
            $notifications->send($target, 'account.risk_reviewed', '帳號風險狀態已調整', $validated['review_note'], null, ['abuse_event_id' => $event->id]);
        }

        $auditLog->record($request, 'admin.abuse_event.reviewed', $event, $validated);
        $moderation->record($request, 'abuse_event.reviewed', $event, $validated['review_note'], ['action_taken' => $validated['action_taken']]);

        return response()->json(['event' => $event->refresh()->load('user')]);
    }

    public function restrictUser(Request $request, User $user, AuditLogService $auditLog, ModerationEventService $moderation, NotificationService $notifications): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'risk_status' => ['required', 'string', 'in:normal,watched,limited,suspended_weight'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $multipliers = config('truthshield.risk_multipliers', []);
        $user->forceFill([
            'risk_status' => $validated['risk_status'],
            'abuse_multiplier' => (float) ($multipliers[$validated['risk_status']] ?? 1.0),
        ])->save();

        $auditLog->record($request, 'admin.user.risk_updated', $user, $validated);
        $moderation->record($request, 'user.risk_updated', $user, $validated['reason'], ['risk_status' => $validated['risk_status']]);
        $notifications->send($user, 'account.risk_updated', '帳號風險狀態已更新', $validated['reason'], null, ['risk_status' => $validated['risk_status']]);

        return response()->json(['user' => $user->fresh()]);
    }

    public function adjustTrust(Request $request, User $user, TrustScoreService $trustScores, AuditLogService $auditLog, ModerationEventService $moderation): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'delta' => ['required', 'numeric', 'min:-5', 'max:5'],
            'reason' => ['required', 'string', 'max:80'],
            'details' => ['required', 'string', 'max:500'],
        ]);

        $updated = $trustScores->adjust($user, (float) $validated['delta'], $validated['reason'], null, $validated['details']);
        $auditLog->record($request, 'admin.trust.adjusted', $updated, $validated);
        $moderation->record($request, 'trust.adjusted', $updated, $validated['details'], ['delta' => (float) $validated['delta']]);

        return response()->json(['user' => $updated->fresh()]);
    }

    public function reviewAppeal(Request $request, Appeal $appeal, AuditLogService $auditLog, ModerationEventService $moderation, NotificationService $notifications): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected'],
            'review_note' => ['required', 'string', 'max:500'],
        ]);

        $appeal->loadMissing('user');
        $appeal->forceFill([
            'status' => $validated['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'],
        ])->save();

        $auditLog->record($request, 'admin.appeal.reviewed', $appeal, $validated);
        $moderation->record($request, 'appeal.reviewed', $appeal, $validated['review_note'], ['status' => $validated['status']]);
        if ($appeal->user) {
            $notifications->send($appeal->user, 'appeal.reviewed', '申訴已完成審核', $validated['review_note'], null, ['appeal_id' => $appeal->id, 'status' => $validated['status']]);
        }

        return response()->json(['appeal' => $appeal->refresh()]);
    }

    public function storeTrustedSource(Request $request, AuditLogService $auditLog): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:40'],
            'trust_bonus' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $source = TrustedEvidenceSource::query()->updateOrCreate(
            ['host' => strtolower($validated['host'])],
            [
                'source_type' => $validated['source_type'] ?? 'archive',
                'trust_bonus' => (float) ($validated['trust_bonus'] ?? 10),
                'is_active' => $validated['is_active'] ?? true,
                'notes' => $validated['notes'] ?? null,
            ],
        );
        $auditLog->record($request, 'admin.trusted_source.upserted', $source, $validated);

        return response()->json(['source' => $source], 201);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && ($user->is_admin || in_array($user->email, config('truthshield.admin_emails', []), true)), 403);
    }

    private function applyRiskAction(User $user, string $action): void
    {
        $status = match ($action) {
            'watch_user' => 'watched',
            'limit_user' => 'limited',
            'suspend_weight' => 'suspended_weight',
            default => $user->risk_status,
        };

        $multipliers = config('truthshield.risk_multipliers', []);
        $user->forceFill([
            'risk_status' => $status,
            'abuse_multiplier' => (float) ($multipliers[$status] ?? $user->abuse_multiplier ?? 1.0),
        ])->save();
    }
}
