<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountEdge;
use App\Models\AbuseEvent;
use App\Models\Appeal;
use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Models\EvidenceReport;
use App\Models\ExtensionSelectorCheck;
use App\Models\ExtensionEvent;
use App\Models\NewsDomainReport;
use App\Models\NewsUrl;
use App\Models\ModerationEvent;
use App\Models\OperationalEvent;
use App\Models\RateLimitPolicy;
use App\Models\TrustedEvidenceSource;
use App\Models\User;
use App\Models\Vote;
use App\Models\ReadSession;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;

class TransparencyController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'users' => User::query()->count(),
            'news_urls' => NewsUrl::query()->count(),
            'votes' => Vote::query()->count(),
            'read_sessions' => ReadSession::query()->count(),
            'trusted_evidence' => Vote::query()->where('evidence_safety', 'trusted')->count(),
            'unread_notifications' => UserNotification::query()->whereNull('read_at')->count(),
            'finalized_news' => NewsUrl::query()->whereNotNull('finalized_at')->count(),
            'pending_domain_reports' => NewsDomainReport::query()->where('status', 'pending')->count(),
            'pending_evidence_reports' => EvidenceReport::query()->where('status', 'pending')->count(),
            'open_abuse_events' => AbuseEvent::query()->where('reviewed', false)->count(),
            'pending_appeals' => Appeal::query()->where('status', 'pending')->count(),
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
            'algorithm_version' => config('truthshield.algorithm_version', 'truthshield-v1'),
            'status_cache_version' => config('truthshield.status_cache_version', 'v1'),
            'weight_distribution' => [
                'normal' => User::query()->where('risk_status', 'normal')->count(),
                'watched' => User::query()->where('risk_status', 'watched')->count(),
                'limited' => User::query()->where('risk_status', 'limited')->count(),
                'suspended_weight' => User::query()->where('risk_status', 'suspended_weight')->count(),
            ],
        ]);
    }
}
