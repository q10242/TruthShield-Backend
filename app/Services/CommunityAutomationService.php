<?php

namespace App\Services;

use App\Models\CommunitySignal;
use App\Models\CommunityTask;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CommunityAutomationService
{
    public function run(): array
    {
        $stats = [
            'domains_auto_approved' => $this->approveDomains(),
            'url_rules_auto_applied' => $this->applyUrlRules(),
            'trusted_sources_auto_approved' => $this->approveTrustedSources(),
            'evidences_soft_demoted' => $this->softDemoteEvidence(),
            'controversy_tasks_created' => $this->createControversyTasks(),
            'maintenance_tasks_synced' => $this->syncMaintenanceTasks(),
        ];

        Cache::store(config('truthshield.status_cache_store'))->forget('transparency:summary:v2');
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
            'community_demoted_evidence' => Evidence::query()->where('moderation_status', 'community_demoted')->count(),
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
                $highRisk = in_array($suggestion->source_type, config('truthshield_community.high_risk_source_types', []), true);

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
        $threshold = (float) config('truthshield_community.thresholds.evidence_unhelpful', 4.0);

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
        $threshold = (float) config('truthshield_community.thresholds.controversy_total_weight', 4.0);

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
            $highRisk = in_array($suggestion->source_type, config('truthshield_community.high_risk_source_types', []), true);
            $this->upsertTask('trusted_source_candidate', $suggestion, $key, '確認可信證據來源', "{$suggestion->host} / {$suggestion->source_type}", 50, '/report-domain', $this->signalSummary('trusted_source', $key), $highRisk);
            $count++;
        });

        return $count;
    }

    private function upsertTask(string $type, Model $subject, string $key, string $title, string $description, int $priority, string $actionUrl, array $metrics = [], bool $escalated = false): CommunityTask
    {
        return CommunityTask::query()->updateOrCreate(
            ['type' => $type, 'subject_key' => $key, 'status' => $escalated ? 'escalated' : 'open'],
            [
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'title' => $title,
                'description' => $description,
                'priority' => $escalated ? max($priority, 85) : $priority,
                'action_url' => $actionUrl,
                'metrics' => $metrics,
            ],
        );
    }

    private function resolveTask(string $type, string $key): void
    {
        CommunityTask::query()
            ->where('type', $type)
            ->where('subject_key', $key)
            ->whereIn('status', ['open', 'escalated'])
            ->update(['status' => 'resolved', 'resolved_at' => now()]);
    }

    private function passes(string $type, array $summary): bool
    {
        return $summary['distinct_users'] >= (int) config('truthshield_community.min_distinct_users', 3)
            && $summary['weighted_score'] >= (float) config("truthshield_community.thresholds.{$type}", 6.0);
    }

    private function isHighRiskDomain(string $domain): bool
    {
        foreach (config('truthshield_community.high_risk_domain_keywords', []) as $keyword) {
            if (str_contains($domain, $keyword)) {
                return true;
            }
        }

        return false;
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
