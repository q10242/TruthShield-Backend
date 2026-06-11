<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunitySignal;
use App\Models\CommunityTask;
use App\Models\Evidence;
use App\Models\EvidenceReaction;
use App\Models\NewsEvent;
use App\Models\NewsUrl;
use App\Models\ReadSession;
use App\Models\TrafficEvent;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PublicCommunityMetricsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->metrics());
    }

    public function metrics(): array
    {
        return Cache::store(config('truthshield.status_cache_store'))->remember(
            'public-community-metrics:v1',
            now()->addSeconds(60),
            fn (): array => $this->buildMetrics(),
        );
    }

    private function buildMetrics(): array
    {
        $sevenDaysAgo = now()->subDays(7);
        $thirtyDaysAgo = now()->subDays(30);

        $active7d = $this->activeRegisteredUserIdsSince($sevenDaysAgo);
        $active30d = $this->activeRegisteredUserIdsSince($thirtyDaysAgo);

        return [
            'generated_at' => now()->toIso8601String(),
            'privacy_note' => 'Only aggregate public community metrics are exposed. No email, IP address, exact reading history, token, or per-user action trail is included.',
            'registered_users_total' => User::query()->count(),
            'registered_users_7d' => $this->usersCreatedSince($sevenDaysAgo),
            'registered_users_30d' => $this->usersCreatedSince($thirtyDaysAgo),
            'active_registered_users_7d' => $active7d->count(),
            'active_registered_users_30d' => $active30d->count(),
            'active_extension_clients_today' => $this->activeExtensionClientsSince(now()->startOfDay()),
            'active_extension_clients_7d' => $this->activeExtensionClientsSince($sevenDaysAgo),
            'active_extension_clients_30d' => $this->activeExtensionClientsSince($thirtyDaysAgo),
            'contributors_total' => [
                'voters' => $this->distinctUsers(Vote::query()),
                'evidence_submitters' => $this->distinctUsers(Evidence::query()),
                'evidence_reviewers' => $this->distinctUsers(EvidenceReaction::query()),
                'community_signal_users' => $this->distinctUsers(CommunitySignal::query()),
            ],
            'activity_30d' => [
                'votes' => Vote::query()->where('created_at', '>=', $thirtyDaysAgo)->count(),
                'evidence_submissions' => Evidence::query()->where('created_at', '>=', $thirtyDaysAgo)->count(),
                'evidence_reactions' => EvidenceReaction::query()->where('created_at', '>=', $thirtyDaysAgo)->count(),
                'community_signals' => CommunitySignal::query()->where('created_at', '>=', $thirtyDaysAgo)->count(),
                'resolved_community_tasks' => CommunityTask::query()->where('status', 'resolved')->where('updated_at', '>=', $thirtyDaysAgo)->count(),
            ],
            'content_totals' => [
                'events' => NewsEvent::query()->where('status', 'active')->count(),
                'news_urls' => NewsUrl::query()->count(),
                'evidence' => Evidence::query()->count(),
                'votes' => Vote::query()->count(),
            ],
            'task_totals' => [
                'open' => CommunityTask::query()->where('status', 'open')->count(),
                'escalated' => CommunityTask::query()->where('status', 'escalated')->count(),
                'resolved' => CommunityTask::query()->where('status', 'resolved')->count(),
            ],
        ];
    }

    private function activeRegisteredUserIdsSince(Carbon $since): Collection
    {
        return collect()
            ->merge($this->userIds(Vote::query()->where('created_at', '>=', $since)))
            ->merge($this->userIds(Evidence::query()->where('created_at', '>=', $since)))
            ->merge($this->userIds(EvidenceReaction::query()->where('created_at', '>=', $since)))
            ->merge($this->userIds(CommunitySignal::query()->where('created_at', '>=', $since)))
            ->merge($this->userIds(TrafficEvent::query()->where('created_at', '>=', $since)))
            ->merge($this->userIds(ReadSession::query()->where(function (Builder $query) use ($since): void {
                $query->where('last_seen_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            })))
            ->filter()
            ->unique()
            ->values();
    }

    private function activeExtensionClientsSince(Carbon $since): int
    {
        return TrafficEvent::query()
            ->where('source', 'extension')
            ->where('created_at', '>=', $since)
            ->whereNotNull('session_hash')
            ->distinct('session_hash')
            ->count('session_hash');
    }

    private function usersCreatedSince(Carbon $since): int
    {
        return User::query()->where('created_at', '>=', $since)->count();
    }

    private function distinctUsers(Builder $query): int
    {
        return $query->whereNotNull('user_id')->distinct('user_id')->count('user_id');
    }

    private function userIds(Builder $query): Collection
    {
        return $query->whereNotNull('user_id')->distinct()->pluck('user_id');
    }
}
