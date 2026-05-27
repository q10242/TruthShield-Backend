<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbuseEvent;
use App\Models\AccountEdge;
use App\Models\ApiClient;
use App\Models\Appeal;
use App\Models\AuditLog;
use App\Models\BugReport;
use App\Models\CommunitySignal;
use App\Models\CommunityTask;
use App\Models\Donation;
use App\Models\Evidence;
use App\Models\EvidenceReport;
use App\Models\ExtensionEvent;
use App\Models\ExtensionSelectorCheck;
use App\Models\ModerationEvent;
use App\Models\NewsChangeReport;
use App\Models\NewsDomainReport;
use App\Models\NewsUrl;
use App\Models\NewsUrlSnapshot;
use App\Models\OfficialResponse;
use App\Models\OfficialResponseReaction;
use App\Models\OperationalEvent;
use App\Models\RateLimitPolicy;
use App\Models\ReadSession;
use App\Models\TrustedEvidenceSource;
use App\Models\User;
use App\Models\UserDataRequest;
use App\Models\UserNotification;
use App\Models\VerifiedClaimant;
use App\Models\Vote;
use App\Services\TrafficAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TransparencyController extends Controller
{
    public function show(TrafficAnalyticsService $traffic): JsonResponse
    {
        return response()->json(Cache::store(config('truthshield.status_cache_store'))->remember('transparency:summary:v6', now()->addSeconds(30), function () use ($traffic): array {
            $trafficSummary = $traffic->publicSummary();

            return [
                'users' => User::query()->count(),
                'news_urls' => NewsUrl::query()->count(),
                'votes' => Vote::query()->count(),
                'read_sessions' => ReadSession::query()->count(),
                'trusted_evidence' => Vote::query()->where('evidence_safety', 'trusted')->count(),
                'unread_notifications' => UserNotification::query()->whereNull('read_at')->count(),
                'finalized_news' => NewsUrl::query()->whereNotNull('finalized_at')->count(),
                'news_snapshots' => NewsUrlSnapshot::query()->count(),
                'changed_news_snapshots' => NewsUrlSnapshot::query()->where('snapshot_type', 'changed')->count(),
                'unavailable_news' => NewsUrl::query()->where('availability_status', 'deleted_or_unavailable')->count(),
                'pending_domain_reports' => NewsDomainReport::query()->where('status', 'pending')->count(),
                'pending_news_change_reports' => NewsChangeReport::query()->where('status', 'pending')->count(),
                'reviewed_news_change_reports' => NewsChangeReport::query()->where('status', 'reviewed')->count(),
                'pending_evidence_reports' => EvidenceReport::query()->where('status', 'pending')->count(),
                'open_abuse_events' => AbuseEvent::query()->where('reviewed', false)->count(),
                'pending_appeals' => Appeal::query()->where('status', 'pending')->count(),
                'pending_user_data_requests' => UserDataRequest::query()->where('status', 'pending')->count(),
                'open_bug_reports' => BugReport::query()->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                'open_security_reports' => BugReport::query()->where('report_type', 'security')->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                'critical_bug_reports' => BugReport::query()->where('severity', 'critical')->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                'pending_verified_claimants' => VerifiedClaimant::query()->where('status', 'pending')->count(),
                'approved_verified_claimants' => VerifiedClaimant::query()->where('status', 'approved')->count(),
                'pending_official_responses' => OfficialResponse::query()->where('status', 'pending')->count(),
                'published_official_responses' => OfficialResponse::query()->where('status', 'published')->count(),
                'official_response_reactions' => OfficialResponseReaction::query()->count(),
                'bot_challenges_24h' => AbuseEvent::query()->where('type', 'challenge_required')->where('created_at', '>=', now()->subDay())->count(),
                'bot_blocks_24h' => AbuseEvent::query()->where('type', 'bot_blocked')->where('created_at', '>=', now()->subDay())->count(),
                'moderation_events_24h' => ModerationEvent::query()->where('created_at', '>=', now()->subDay())->count(),
                'extension_failures_24h' => ExtensionEvent::query()->where('success', false)->where('created_at', '>=', now()->subDay())->count(),
                'audit_events_24h' => AuditLog::query()->where('created_at', '>=', now()->subDay())->count(),
                'account_edges' => AccountEdge::query()->count(),
                'high_risk_account_edges' => AccountEdge::query()->where('score', '>=', 50)->count(),
                'active_api_clients' => ApiClient::query()->where('status', 'active')->count(),
                'operational_events_24h' => OperationalEvent::query()->where('created_at', '>=', now()->subDay())->count(),
                'selector_failures_24h' => ExtensionSelectorCheck::query()->actionableFailures()->where('checked_at', '>=', now()->subDay())->count(),
                'active_trusted_evidence_sources' => TrustedEvidenceSource::query()->where('is_active', true)->count(),
                'active_rate_limit_policies' => RateLimitPolicy::query()->where('is_active', true)->count(),
                'community_open_tasks' => CommunityTask::query()->where('status', 'open')->count(),
                'community_escalated_tasks' => CommunityTask::query()->where('status', 'escalated')->count(),
                'community_resolved_tasks' => CommunityTask::query()->where('status', 'resolved')->count(),
                'community_signals' => CommunitySignal::query()->count(),
                'community_authenticated_signals' => CommunitySignal::query()->whereNotNull('user_id')->count(),
                'community_demoted_evidence' => Evidence::query()->where('moderation_status', 'community_demoted')->count(),
                'community_signal_abuse_events' => AbuseEvent::query()->where('type', 'community_signal_spike')->where('reviewed', false)->count(),
                'donation_total_amount' => (int) Donation::query()->where('status', Donation::STATUS_PAID)->sum('amount'),
                'donation_paid_count' => Donation::query()->where('status', Donation::STATUS_PAID)->count(),
                'donation_month_amount' => (int) Donation::query()->where('status', Donation::STATUS_PAID)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
                'traffic' => $trafficSummary,
                ...$trafficSummary,
                'algorithm_version' => config('truthshield.algorithm_version', 'truthshield-v1'),
                'status_cache_version' => config('truthshield.status_cache_version', 'v1'),
                'weight_distribution' => [
                    'normal' => User::query()->where('risk_status', 'normal')->count(),
                    'watched' => User::query()->where('risk_status', 'watched')->count(),
                    'limited' => User::query()->where('risk_status', 'limited')->count(),
                    'suspended_weight' => User::query()->where('risk_status', 'suspended_weight')->count(),
                ],
                'governance_distribution' => [
                    'evidence_reports_pending' => EvidenceReport::query()->where('status', 'pending')->count(),
                    'appeals_pending' => Appeal::query()->where('status', 'pending')->count(),
                    'change_reports_pending' => NewsChangeReport::query()->where('status', 'pending')->count(),
                    'abuse_events_open' => AbuseEvent::query()->where('reviewed', false)->count(),
                    'community_tasks_open' => CommunityTask::query()->where('status', 'open')->count(),
                    'community_tasks_escalated' => CommunityTask::query()->where('status', 'escalated')->count(),
                    'bug_reports_open' => BugReport::query()->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                    'security_reports_open' => BugReport::query()->where('report_type', 'security')->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                ],
                'bug_report_distribution' => [
                    'new' => BugReport::query()->where('status', 'new')->count(),
                    'triaged' => BugReport::query()->where('status', 'triaged')->count(),
                    'in_progress' => BugReport::query()->where('status', 'in_progress')->count(),
                    'fixed' => BugReport::query()->where('status', 'fixed')->count(),
                    'wont_fix' => BugReport::query()->where('status', 'wont_fix')->count(),
                ],
                'official_response_distribution' => [
                    'pending' => OfficialResponse::query()->where('status', 'pending')->count(),
                    'published' => OfficialResponse::query()->where('status', 'published')->count(),
                    'hidden' => OfficialResponse::query()->where('status', 'hidden')->count(),
                    'rejected' => OfficialResponse::query()->where('status', 'rejected')->count(),
                ],
                'claimant_distribution' => [
                    'pending' => VerifiedClaimant::query()->where('status', 'pending')->count(),
                    'approved' => VerifiedClaimant::query()->where('status', 'approved')->count(),
                    'rejected' => VerifiedClaimant::query()->where('status', 'rejected')->count(),
                ],
                'bot_protection_distribution' => [
                    'challenge_required_24h' => AbuseEvent::query()->where('type', 'challenge_required')->where('created_at', '>=', now()->subDay())->count(),
                    'blocked_24h' => AbuseEvent::query()->where('type', 'bot_blocked')->where('created_at', '>=', now()->subDay())->count(),
                    'community_signal_spikes' => AbuseEvent::query()->where('type', 'community_signal_spike')->where('created_at', '>=', now()->subDay())->count(),
                ],
                'governance_pressure_score' => min(100, (
                    EvidenceReport::query()->where('status', 'pending')->count()
                    + Appeal::query()->where('status', 'pending')->count()
                    + BugReport::query()->whereIn('status', ['new', 'triaged', 'in_progress'])->count()
                    + BugReport::query()->where('severity', 'critical')->whereIn('status', ['new', 'triaged', 'in_progress'])->count()
                    + NewsChangeReport::query()->where('status', 'pending')->count()
                    + AbuseEvent::query()->where('reviewed', false)->count() * 2
                ) * 10),
            ];
        }));
    }
}
