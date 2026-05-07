<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountEdge;
use App\Models\AbuseEvent;
use App\Models\Appeal;
use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Models\Donation;
use App\Models\EvidenceReport;
use App\Models\ExtensionSelectorCheck;
use App\Models\ExtensionEvent;
use App\Models\NewsDomainReport;
use App\Models\NewsChangeReport;
use App\Models\NewsUrl;
use App\Models\NewsUrlSnapshot;
use App\Models\ModerationEvent;
use App\Models\OperationalEvent;
use App\Models\RateLimitPolicy;
use App\Models\TrustedEvidenceSource;
use App\Models\User;
use App\Models\UserDataRequest;
use App\Models\Vote;
use App\Models\ReadSession;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TransparencyController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(Cache::store(config('truthshield.status_cache_store'))->remember('transparency:summary:v2', now()->addSeconds(30), fn () => [
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
            'moderation_events_24h' => ModerationEvent::query()->where('created_at', '>=', now()->subDay())->count(),
            'extension_failures_24h' => ExtensionEvent::query()->where('success', false)->where('created_at', '>=', now()->subDay())->count(),
            'audit_events_24h' => AuditLog::query()->where('created_at', '>=', now()->subDay())->count(),
            'account_edges' => AccountEdge::query()->count(),
            'high_risk_account_edges' => AccountEdge::query()->where('score', '>=', 50)->count(),
            'active_api_clients' => ApiClient::query()->where('status', 'active')->count(),
            'operational_events_24h' => OperationalEvent::query()->where('created_at', '>=', now()->subDay())->count(),
            'selector_failures_24h' => ExtensionSelectorCheck::query()->where('success', false)->where('checked_at', '>=', now()->subDay())->count(),
            'active_trusted_evidence_sources' => TrustedEvidenceSource::query()->where('is_active', true)->count(),
            'active_rate_limit_policies' => RateLimitPolicy::query()->where('is_active', true)->count(),
            'donation_total_amount' => (int) Donation::query()->where('status', Donation::STATUS_PAID)->sum('amount'),
            'donation_paid_count' => Donation::query()->where('status', Donation::STATUS_PAID)->count(),
            'donation_month_amount' => (int) Donation::query()->where('status', Donation::STATUS_PAID)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
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
            ],
            'governance_pressure_score' => min(100, (
                EvidenceReport::query()->where('status', 'pending')->count()
                + Appeal::query()->where('status', 'pending')->count()
                + NewsChangeReport::query()->where('status', 'pending')->count()
                + AbuseEvent::query()->where('reviewed', false)->count() * 2
            ) * 10),
        ]));
    }
}
