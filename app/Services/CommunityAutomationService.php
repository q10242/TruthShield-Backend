<?php

namespace App\Services;

use App\Models\CommunitySignal;
use App\Models\CommunityTask;
use App\Models\AbuseEvent;
use App\Models\Evidence;
use App\Models\MediaOutlet;
use App\Models\ModerationEvent;
use App\Models\NewsDomain;
use App\Models\NewsDomainReport;
use App\Models\NewsUrl;
use App\Models\TrustedEvidenceSource;
use App\Models\TrustedSourceSuggestion;
use App\Models\UrlClassificationReport;
use App\Models\Vote;
use App\Models\YoutubeChannelReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CommunityAutomationService
{
    private const OFFICIAL_RESPONSE_TAG_SLUGS = [
        'lack-of-balance',
        'single-source',
    ];

    public function __construct(private readonly CommunityPolicyService $policy)
    {
    }

    public function run(): array
    {
        $stats = [
            'domains_auto_approved' => $this->approveDomains(),
            'url_rules_auto_applied' => $this->applyUrlRules(),
            'trusted_sources_auto_approved' => $this->approveTrustedSources(),
            'evidences_soft_demoted' => $this->softDemoteEvidence(),
            'controversy_tasks_created' => $this->createControversyTasks(),
            'maintenance_tasks_synced' => $this->syncMaintenanceTasks(),
            'official_response_tasks_created' => $this->createOfficialResponseTasks(),
            'community_abuse_events_created' => $this->detectCommunitySignalAbuse(),
            'stale_tasks_expired' => $this->expireStaleTasks(),
        ];

        Cache::store(config('truthshield.status_cache_store'))->forget('transparency:summary:v2');
        Cache::store(config('truthshield.status_cache_store'))->forget('transparency:summary:v3');
        Cache::store(config('truthshield.status_cache_store'))->forget('vision:readiness:v2');

        return $stats;
    }

    public function stats(): array
    {
        return [
            'open_tasks' => CommunityTask::query()->where('status', 'open')->count(),
            'escalated_tasks' => CommunityTask::query()->where('status', 'escalated')->count(),
            'resolved_tasks' => CommunityTask::query()->where('status', 'resolved')->count(),
            'signals' => CommunitySignal::query()->count(),
            'authenticated_signals' => CommunitySignal::query()->whereNotNull('user_id')->count(),
            'auto_approved_domains' => NewsDomainReport::query()->where('status', 'community_approved')->count(),
            'auto_applied_url_rules' => UrlClassificationReport::query()->where('status', 'community_approved')->count(),
            'auto_approved_sources' => TrustedSourceSuggestion::query()->where('status', 'community_approved')->count(),
            'pending_youtube_channel_reports' => YoutubeChannelReport::query()->where('status', 'pending')->count(),
            'community_demoted_evidence' => Evidence::query()->where('moderation_status', 'community_demoted')->count(),
            'official_response_tasks' => CommunityTask::query()->where('type', 'needs_official_response')->whereIn('status', ['open', 'escalated'])->count(),
            'auto_governance_events' => ModerationEvent::query()->where('event_type', 'like', 'community.%')->count(),
            'automation_success_rate' => $this->automationSuccessRate(),
            'community_signal_abuse_events' => AbuseEvent::query()->where('type', 'community_signal_spike')->where('reviewed', false)->count(),
        ];
    }

    public function signalSummary(string $signalType, string $subjectKey): array
    {
        $base = CommunitySignal::query()
            ->where('signal_type', $signalType)
            ->where('subject_key', $subjectKey);

        return [
            'weighted_score' => round((float) (clone $base)->whereNotNull('user_id')->sum('weight_score'), 4),
            'distinct_users' => (int) (clone $base)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'anonymous_signals' => (int) (clone $base)->whereNull('user_id')->count(),
            'total_signals' => (int) (clone $base)->count(),
            'required_users' => $this->policy->minDistinctUsers(),
        ];
    }

    public function taskDetail(CommunityTask $task): array
    {
        $signalType = $this->signalTypeForTask($task->type);
        $summary = $signalType ? $this->signalSummary($signalType, $task->subject_key) : ($task->metrics ?: []);
        $thresholdKey = $this->thresholdKeyForTask($task->type);
        $requiredScore = $thresholdKey ? $this->policy->threshold($thresholdKey, 0) : 0;

        return [
            'task' => $task,
            'signal_type' => $signalType,
            'summary' => $summary,
            'gap' => [
                'required_users' => $this->policy->minDistinctUsers(),
                'remaining_users' => max(0, $this->policy->minDistinctUsers() - (int) ($summary['distinct_users'] ?? 0)),
                'required_score' => $requiredScore,
                'remaining_score' => max(0, round($requiredScore - (float) ($summary['weighted_score'] ?? 0), 4)),
            ],
            'actions' => $this->actionsForTask($task),
        ];
    }

    private function approveDomains(): int
    {
        $count = 0;

        NewsDomainReport::query()
            ->where('status', 'pending')
            ->get()
            ->each(function (NewsDomainReport $report) use (&$count): void {
                $summary = $this->signalSummary('domain_report', $report->domain);
                if (! $this->passes('domain_report', $summary) || $this->isHighRiskDomain($report->domain)) {
                    $this->upsertTask('domain_candidate', $report, $report->domain, '確認未收錄新聞站', "社群回報 {$report->domain}，需要確認是否為新聞站。", 75, '/report-domain', $summary, $this->isHighRiskDomain($report->domain));
                    return;
                }

                $outlet = MediaOutlet::query()->firstOrCreate(
                    ['slug' => Str::slug($report->domain)],
                    ['name' => $report->page_title ?: $report->domain, 'type' => 'news', 'is_active' => true],
                );

                NewsDomain::query()->updateOrCreate(
                    ['domain' => $report->domain],
                    [
                        'media_outlet_id' => $outlet->id,
                        'name' => $report->page_title ?: $report->domain,
                        'is_active' => true,
                        'notes' => trim(($report->note ?: '') . "\nCommunity auto-approved with weighted score {$summary['weighted_score']}."),
                    ],
                );

                $report->forceFill(['status' => 'community_approved'])->save();
                $this->resolveTask('domain_candidate', $report->domain);
                $this->recordEvent('community.domain.auto_approved', $report, "社群共識自動收錄新聞站 {$report->domain}", $summary);
                $count++;
            });

        return $count;
    }

    private function applyUrlRules(): int
    {
        $count = 0;

        UrlClassificationReport::query()
            ->where('status', 'pending')
            ->whereNotNull('suggested_pattern')
            ->get()
            ->each(function (UrlClassificationReport $report) use (&$count): void {
                $key = $this->urlClassificationKey($report->domain, $report->path_signature, $report->classification);
                $summary = $this->signalSummary('url_classification', $key);

                if (! $this->passes('url_classification', $summary) || $this->isHighRiskDomain($report->domain)) {
                    $this->upsertTask('url_rule_candidate', $report, $key, '確認新聞網址規則', "{$report->domain} 的 {$report->classification} 規則需要社群確認。", 60, '/report-domain', $summary, $this->isHighRiskDomain($report->domain));
                    return;
                }

                $field = $report->classification === 'article' ? 'article_url_pattern' : 'list_url_pattern';
                NewsDomain::query()->updateOrCreate(
                    ['domain' => $report->domain],
                    [
                        'name' => $report->domain,
                        'is_active' => true,
                        $field => $report->suggested_pattern,
                        'notes' => "Community auto-applied {$report->classification} URL rule.",
                    ],
                );

                $report->forceFill(['status' => 'community_approved'])->save();
                $this->resolveTask('url_rule_candidate', $key);
                $this->recordEvent('community.url_rule.auto_applied', $report, "社群共識自動套用 {$report->domain} URL 規則", $summary);
                $count++;
            });

        return $count;
    }

    private function approveTrustedSources(): int
    {
        $count = 0;

        TrustedSourceSuggestion::query()
            ->where('status', 'pending')
            ->get()
            ->each(function (TrustedSourceSuggestion $suggestion) use (&$count): void {
                $key = $this->trustedSourceKey($suggestion->host, $suggestion->source_type);
                $summary = $this->signalSummary('trusted_source', $key);
                $highRisk = in_array($suggestion->source_type, $this->policy->all()['high_risk_source_types'], true);

                if (! $this->passes('trusted_source', $summary) || $highRisk) {
                    $this->upsertTask('trusted_source_candidate', $suggestion, $key, '確認可信證據來源', "{$suggestion->host} 被提議為 {$suggestion->source_type} 來源。", 65, '/report-domain', $summary, $highRisk);
                    return;
                }

                TrustedEvidenceSource::query()->updateOrCreate(
                    ['host' => $suggestion->host],
                    [
                        'source_type' => $suggestion->source_type,
                        'trust_bonus' => $this->trustBonusForSourceType($suggestion->source_type),
                        'is_active' => true,
                        'notes' => trim(($suggestion->note ?: '') . "\nCommunity auto-approved with weighted score {$summary['weighted_score']}."),
                    ],
                );

                $suggestion->forceFill(['status' => 'community_approved'])->save();
                $this->resolveTask('trusted_source_candidate', $key);
                $this->recordEvent('community.trusted_source.auto_approved', $suggestion, "社群共識自動加入可信證據來源 {$suggestion->host}", $summary);
                $count++;
            });

        return $count;
    }

    private function softDemoteEvidence(): int
    {
        $count = 0;
        $threshold = $this->policy->threshold('evidence_unhelpful', 4.0);

        Evidence::query()
            ->where('moderation_status', 'visible')
            ->whereHas('vote.reactions', fn ($query) => $query->where('helpful', false))
            ->with('vote')
            ->get()
            ->each(function (Evidence $evidence) use (&$count, $threshold): void {
                $vote = $evidence->vote;
                if (! $vote) {
                    return;
                }

                $unhelpful = (float) $vote->reactions()->where('helpful', false)->sum('weight_score');
                $helpful = (float) $vote->reactions()->where('helpful', true)->sum('weight_score');
                if (($unhelpful - $helpful) < $threshold) {
                    $this->upsertTask('evidence_quality_review', $evidence, "evidence:{$evidence->id}", '協助確認證據品質', '這則證據評價分裂，需要社群補充有用/沒幫助判斷。', 55, '/evidence-library?focus=community', [
                        'helpful_weight' => round($helpful, 4),
                        'unhelpful_weight' => round($unhelpful, 4),
                    ]);
                    return;
                }

                $evidence->forceFill([
                    'moderation_status' => 'community_demoted',
                    'quality_score' => max(0, round((float) $evidence->quality_score - 15, 2)),
                    'metadata' => [
                        ...($evidence->metadata ?? []),
                        'community_demoted_at' => now()->toJSON(),
                        'helpful_weight' => round($helpful, 4),
                        'unhelpful_weight' => round($unhelpful, 4),
                    ],
                ])->save();

                $this->recordEvent('community.evidence.soft_demoted', $evidence, '社群加權評價顯示此證據幫助度不足，已降低排序但未隱藏。', [
                    'helpful_weight' => round($helpful, 4),
                    'unhelpful_weight' => round($unhelpful, 4),
                ]);
                $count++;
            });

        return $count;
    }

    private function createControversyTasks(): int
    {
        $created = 0;
        $threshold = $this->policy->threshold('controversy_total_weight', 4.0);

        NewsUrl::query()
            ->whereNull('finalized_at')
            ->whereHas('votes')
            ->latest()
            ->limit(200)
            ->get()
            ->each(function (NewsUrl $newsUrl) use (&$created, $threshold): void {
                $weights = Vote::query()
                    ->where('news_url_id', $newsUrl->id)
                    ->selectRaw('tag_id, sum(weight_score) as weight')
                    ->groupBy('tag_id')
                    ->orderByDesc('weight')
                    ->pluck('weight')
                    ->map(fn ($weight) => (float) $weight)
                    ->values();

                $total = $weights->sum();
                if ($weights->count() < 2 || $total < $threshold) {
                    return;
                }

                $top = $weights[0] ?? 0;
                $second = $weights[1] ?? 0;
                if ($top <= 0 || ($top - $second) / max(1, $total) > 0.2) {
                    return;
                }

                $task = $this->upsertTask('controversial_news', $newsUrl, "news:{$newsUrl->id}", '高爭議新聞需要補證據', $newsUrl->title_snapshot ?: $newsUrl->normalized_url, 80, "/news/{$newsUrl->id}", [
                    'total_weight' => round($total, 4),
                    'top_weight' => round($top, 4),
                    'second_weight' => round($second, 4),
                ]);

                if ($task->wasRecentlyCreated) {
                    $created++;
                }
            });

        return $created;
    }

    private function syncMaintenanceTasks(): int
    {
        $count = 0;

        NewsDomainReport::query()->where('status', 'pending')->get()->each(function (NewsDomainReport $report) use (&$count): void {
            $summary = $this->signalSummary('domain_report', $report->domain);
            $this->upsertTask('domain_candidate', $report, $report->domain, '確認未收錄新聞站', "確認 {$report->domain} 是否應納入插件監控。", 55, '/report-domain', $summary, $this->isHighRiskDomain($report->domain));
            $count++;
        });

        UrlClassificationReport::query()->where('status', 'pending')->get()->each(function (UrlClassificationReport $report) use (&$count): void {
            $key = $this->urlClassificationKey($report->domain, $report->path_signature, $report->classification);
            $this->upsertTask('url_rule_candidate', $report, $key, '確認新聞頁規則', "{$report->domain} / {$report->classification}", 50, '/report-domain', $this->signalSummary('url_classification', $key), $this->isHighRiskDomain($report->domain));
            $count++;
        });

        TrustedSourceSuggestion::query()->where('status', 'pending')->get()->each(function (TrustedSourceSuggestion $suggestion) use (&$count): void {
            $key = $this->trustedSourceKey($suggestion->host, $suggestion->source_type);
            $highRisk = in_array($suggestion->source_type, $this->policy->all()['high_risk_source_types'], true);
            $this->upsertTask('trusted_source_candidate', $suggestion, $key, '確認可信證據來源', "{$suggestion->host} / {$suggestion->source_type}", 50, '/report-domain', $this->signalSummary('trusted_source', $key), $highRisk);
            $count++;
        });

        YoutubeChannelReport::query()->where('status', 'pending')->get()->each(function (YoutubeChannelReport $report) use (&$count): void {
            $key = $this->youtubeChannelKey($report);
            $label = $report->channel_title ?: ($report->handle ? '@' . $report->handle : $report->channel_url);
            $this->upsertTask('youtube_channel_candidate', $report, $key, '確認 YouTube 新聞頻道', "{$label} / {$report->channel_type}", 45, '/report-domain', $this->signalSummary('youtube_channel_report', $key), true);
            $count++;
        });

        return $count;
    }

    private function createOfficialResponseTasks(): int
    {
        $created = 0;
        $this->resolveOfficialResponseTasksWithoutNeed();

        NewsUrl::query()
            ->whereDoesntHave('officialResponses', fn ($query) => $query->whereIn('status', ['published', 'pending']))
            ->whereHas('votes')
            ->with(['votes.user:id', 'votes.tag:id,slug,severity'])
            ->withCount('votes')
            ->orderByDesc('votes_count')
            ->limit(100)
            ->get()
            ->each(function (NewsUrl $newsUrl) use (&$created): void {
                $metrics = $this->officialResponseMetrics($newsUrl);
                if (! $this->needsOfficialResponseTask($metrics)) {
                    return;
                }

                $task = $this->upsertTask(
                    'needs_official_response',
                    $newsUrl,
                    "news:official-response:{$newsUrl->id}",
                    '可能需要官方或本人澄清',
                    $this->officialResponseTaskDescription($newsUrl, $metrics),
                    $this->officialResponsePriority($newsUrl, $metrics),
                    "/news/{$newsUrl->id}",
                    $metrics,
                );

                if ($task->wasRecentlyCreated) {
                    $created++;
                }
            });

        return $created;
    }

    private function resolveOfficialResponseTasksWithoutNeed(): void
    {
        CommunityTask::query()
            ->where('type', 'needs_official_response')
            ->whereIn('status', ['open', 'escalated'])
            ->where('subject_type', NewsUrl::class)
            ->with('subject')
            ->get()
            ->each(function (CommunityTask $task): void {
                $newsUrl = $task->subject;
                if (! $newsUrl instanceof NewsUrl) {
                    $task->forceFill([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'resolved_reason' => 'subject_missing',
                    ])->save();
                    return;
                }

                $newsUrl->load(['votes.user:id', 'votes.tag:id,slug,severity', 'officialResponses']);
                $hasResponse = $newsUrl->officialResponses
                    ->whereIn('status', ['published', 'pending'])
                    ->isNotEmpty();

                if ($hasResponse || ! $this->needsOfficialResponseTask($this->officialResponseMetrics($newsUrl))) {
                    $task->forceFill([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'resolved_reason' => $hasResponse ? 'official_response_exists' : 'official_response_not_required',
                    ])->save();
                }
            });
    }

    private function officialResponseMetrics(NewsUrl $newsUrl): array
    {
        $votes = $newsUrl->relationLoaded('votes') ? $newsUrl->votes : $newsUrl->votes()->with('tag')->get();
        $negativeVotes = $votes->filter(fn (Vote $vote): bool => $vote->tag?->severity !== 'positive');
        $rightOfReplyVotes = $negativeVotes->filter(fn (Vote $vote): bool => in_array($vote->tag?->slug, self::OFFICIAL_RESPONSE_TAG_SLUGS, true));
        $triggerSlugs = $rightOfReplyVotes
            ->map(fn (Vote $vote): ?string => $vote->tag?->slug)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'votes_count' => (int) $votes->count(),
            'negative_vote_count' => (int) $negativeVotes->count(),
            'negative_weight' => round((float) $negativeVotes->sum('weight_score'), 4),
            'right_of_reply_vote_count' => (int) $rightOfReplyVotes->count(),
            'right_of_reply_user_count' => (int) $rightOfReplyVotes->pluck('user_id')->filter()->unique()->count(),
            'right_of_reply_weight' => round((float) $rightOfReplyVotes->sum('weight_score'), 4),
            'official_response_threshold' => $this->policy->threshold('official_response_request', 3.0),
            'required_users' => $this->policy->minDistinctUsers(),
            'trigger_tag_slugs' => $triggerSlugs,
            'reason_code' => 'right_of_reply_negative_consensus',
            'finalized_at' => $newsUrl->finalized_at?->toJSON(),
        ];
    }

    private function needsOfficialResponseTask(array $metrics): bool
    {
        return (int) $metrics['right_of_reply_user_count'] >= $this->policy->minDistinctUsers()
            && (float) $metrics['right_of_reply_weight'] >= $this->policy->threshold('official_response_request', 3.0);
    }

    private function officialResponseTaskDescription(NewsUrl $newsUrl, array $metrics): string
    {
        $title = $newsUrl->title_snapshot ?: $newsUrl->normalized_url;
        $tags = implode(', ', $metrics['trigger_tag_slugs'] ?: self::OFFICIAL_RESPONSE_TAG_SLUGS);

        return "{$title}：社群的可回應型負面標籤已達門檻（{$tags}），建議尋找媒體、當事人或機構回應。";
    }

    private function officialResponsePriority(NewsUrl $newsUrl, array $metrics): int
    {
        $threshold = max(1.0, (float) $metrics['official_response_threshold']);
        $priority = (float) $metrics['right_of_reply_weight'] >= $threshold * 2 ? 75 : 65;

        return $newsUrl->finalized_at ? min(90, $priority + 5) : $priority;
    }

    private function upsertTask(string $type, Model $subject, string $key, string $title, string $description, int $priority, string $actionUrl, array $metrics = [], bool $escalated = false): CommunityTask
    {
        $policy = $this->policy->all();
        $existing = CommunityTask::query()
            ->where('type', $type)
            ->where('subject_key', $key)
            ->whereIn('status', ['open', 'escalated'])
            ->first();
        $status = $escalated || $existing?->status === 'escalated' ? 'escalated' : 'open';
        $attributes = $existing
            ? ['id' => $existing->id]
            : ['type' => $type, 'subject_key' => $key, 'status' => $status];

        return CommunityTask::query()->updateOrCreate(
            $attributes,
            [
                'type' => $type,
                'subject_key' => $key,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'title' => $title,
                'description' => $description,
                'priority' => $escalated ? max($priority, 85) : $priority,
                'status' => $status,
                'action_url' => $actionUrl,
                'metrics' => $metrics,
                'generation_snapshot' => [
                    'reason' => $description,
                    'policy' => [
                        'min_distinct_users' => $policy['min_distinct_users'],
                        'thresholds' => $policy['thresholds'],
                    ],
                    'metrics' => $metrics,
                    'generated_at' => now()->toJSON(),
                ],
                'expires_at' => now()->addDays(max(1, (int) $policy['task_stale_days'])),
            ],
        );
    }

    private function resolveTask(string $type, string $key): void
    {
        CommunityTask::query()
            ->where('type', $type)
            ->where('subject_key', $key)
            ->whereIn('status', ['open', 'escalated'])
            ->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_reason' => 'community_consensus_applied']);
    }

    private function passes(string $type, array $summary): bool
    {
        return $summary['distinct_users'] >= $this->policy->minDistinctUsers()
            && $summary['weighted_score'] >= $this->policy->threshold($type, 6.0);
    }

    private function isHighRiskDomain(string $domain): bool
    {
        foreach ($this->policy->all()['high_risk_domain_keywords'] as $keyword) {
            if (str_contains($domain, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function expireStaleTasks(): int
    {
        return CommunityTask::query()
            ->whereIn('status', ['open', 'escalated'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_reason' => 'expired_without_consensus']);
    }

    private function detectCommunitySignalAbuse(): int
    {
        $created = 0;

        CommunitySignal::query()
            ->where('created_at', '>=', now()->subMinutes(10))
            ->whereNotNull('user_id')
            ->selectRaw('signal_type, subject_key, count(*) as signal_count, count(distinct user_id) as user_count, sum(weight_score) as weight_sum')
            ->groupBy('signal_type', 'subject_key')
            ->havingRaw('count(*) >= 5')
            ->get()
            ->each(function ($cluster) use (&$created): void {
                $exists = AbuseEvent::query()
                    ->where('type', 'community_signal_spike')
                    ->where('created_at', '>=', now()->subHour())
                    ->where('metadata->signal_type', $cluster->signal_type)
                    ->where('metadata->subject_key', $cluster->subject_key)
                    ->exists();

                if ($exists) {
                    return;
                }

                AbuseEvent::query()->create([
                    'type' => 'community_signal_spike',
                    'severity' => ((int) $cluster->signal_count >= 10 || (float) $cluster->weight_sum >= 10) ? 'high' : 'medium',
                    'metadata' => [
                        'signal_type' => $cluster->signal_type,
                        'subject_key' => $cluster->subject_key,
                        'signal_count' => (int) $cluster->signal_count,
                        'user_count' => (int) $cluster->user_count,
                        'weight_sum' => round((float) $cluster->weight_sum, 4),
                        'window' => '10m',
                    ],
                ]);
                $created++;
            });

        return $created;
    }

    private function automationSuccessRate(): int
    {
        $autoEvents = ModerationEvent::query()->where('event_type', 'like', 'community.%')->count();
        if ($autoEvents === 0) {
            return 100;
        }

        $appealEvents = ModerationEvent::query()
            ->where('event_type', 'appeal.created')
            ->where('metadata->subject_type', 'like', '%community%')
            ->count();

        return max(0, min(100, (int) round((($autoEvents - $appealEvents) / $autoEvents) * 100)));
    }

    public function signalTypeForTask(string $taskType): ?string
    {
        return match ($taskType) {
            'domain_candidate' => 'domain_report',
            'url_rule_candidate' => 'url_classification',
            'trusted_source_candidate' => 'trusted_source',
            'youtube_channel_candidate' => 'youtube_channel_report',
            'evidence_quality_review' => 'evidence_unhelpful',
            'needs_official_response' => 'official_response_request',
            'fact_check_request' => 'fact_check_request',
            default => null,
        };
    }

    public function thresholdKeyForTask(string $taskType): ?string
    {
        return match ($taskType) {
            'domain_candidate' => 'domain_report',
            'url_rule_candidate' => 'url_classification',
            'trusted_source_candidate' => 'trusted_source',
            'youtube_channel_candidate' => 'domain_report',
            'evidence_quality_review' => 'evidence_unhelpful',
            'needs_official_response' => 'official_response_request',
            'fact_check_request' => 'fact_check_request',
            default => null,
        };
    }

    private function actionsForTask(CommunityTask $task): array
    {
        return match ($task->type) {
            'domain_candidate' => [
                ['value' => 'confirm_news_domain', 'label' => '我確認這是新聞站'],
                ['value' => 'reject_news_domain', 'label' => '我認為這不是新聞站'],
            ],
            'url_rule_candidate' => [
                ['value' => 'confirm_url_rule', 'label' => '我確認這個 URL 規則正確'],
                ['value' => 'reject_url_rule', 'label' => '這個規則可能會誤判'],
            ],
            'trusted_source_candidate' => [
                ['value' => 'confirm_trusted_source', 'label' => '我確認這是穩定可信來源'],
                ['value' => 'reject_trusted_source', 'label' => '這個來源不適合自動信任'],
            ],
            'youtube_channel_candidate' => [
                ['value' => 'confirm_youtube_channel', 'label' => '我確認這是新聞或公共議題頻道'],
                ['value' => 'reject_youtube_channel', 'label' => '這不適合作為新聞來源'],
            ],
            'evidence_quality_review' => [
                ['value' => 'confirm_evidence_unhelpful', 'label' => '這個證據沒幫助'],
                ['value' => 'reject_evidence_unhelpful', 'label' => '這個證據仍有幫助'],
            ],
            'controversial_news' => [
                ['value' => 'needs_more_evidence', 'label' => '這則新聞需要更多證據'],
            ],
            'needs_official_response' => [
                ['value' => 'needs_official_response', 'label' => '這則新聞需要官方或本人澄清'],
                ['value' => 'reject_official_response_need', 'label' => '目前不需要官方澄清'],
            ],
            'fact_check_request' => [
                ['value' => 'request_fact_check', 'label' => '這則新聞需要求證'],
                ['value' => 'reject_fact_check_request', 'label' => '目前不需要求證任務'],
            ],
            default => [],
        };
    }

    private function trustBonusForSourceType(string $type): float
    {
        return match ($type) {
            'government', 'fact_check' => 15,
            'archive' => 12,
            'cloud_drive' => 8,
            'image_host' => 6,
            default => 5,
        };
    }

    public function urlClassificationKey(string $domain, string $pathSignature, string $classification): string
    {
        return "{$domain}|{$pathSignature}|{$classification}";
    }

    public function trustedSourceKey(string $host, string $sourceType): string
    {
        return "{$host}|{$sourceType}";
    }

    private function youtubeChannelKey(YoutubeChannelReport $report): string
    {
        if ($report->channel_id) {
            return "channel:{$report->channel_id}";
        }

        if ($report->handle) {
            return "handle:{$report->handle}";
        }

        return "url:{$report->channel_url}";
    }

    private function recordEvent(string $type, Model $subject, string $reason, array $metadata): void
    {
        ModerationEvent::query()->create([
            'event_type' => $type,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'public_reason' => $reason,
            'metadata' => [
                ...$metadata,
                'automation' => 'community_self_management_v1',
            ],
        ]);
    }
}
