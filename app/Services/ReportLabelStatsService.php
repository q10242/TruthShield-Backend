<?php

namespace App\Services;

use App\Models\Journalist;
use App\Models\MediaOutlet;
use App\Models\NewsUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportLabelStatsService
{
    /** @deprecated kept for backward-compat constant consumers */
    public const TRACKED_TAG_SLUG = 'clickbait-title';

    public function mediaStats(MediaOutlet $mediaOutlet, int $articleLimit = 20, bool $includePeriods = false): array
    {
        return $this->statsForNewsQuery(
            NewsUrl::query()->where('media_outlet_id', $mediaOutlet->id),
            $articleLimit,
            $includePeriods,
        );
    }

    public function journalistStats(Journalist $journalist, int $articleLimit = 20): array
    {
        return $this->statsForNewsQuery(
            NewsUrl::query()->whereHas('journalistMatches', function (Builder $query) use ($journalist): void {
                $query->where('journalist_id', $journalist->id)
                    ->where('review_status', 'confirmed');
            }),
            $articleLimit,
        );
    }

    public function mediaContext(MediaOutlet $mediaOutlet): array
    {
        $stats = $this->mediaStats($mediaOutlet, 0);

        return [
            'type' => 'media_outlet',
            'id' => $mediaOutlet->id,
            'name' => $mediaOutlet->name,
            'slug' => $mediaOutlet->slug,
            'stats' => $this->summaryOnly($stats),
        ];
    }

    public function journalistContextForNews(NewsUrl $newsUrl): array
    {
        $matches = $newsUrl->journalistMatches()
            ->with('journalist:id,display_name,canonical_name,media_outlet_id,status')
            ->whereIn('review_status', ['confirmed', 'suspected'])
            ->whereHas('journalist', fn (Builder $query) => $query->where('status', 'active'))
            ->latest()
            ->limit(5)
            ->get();

        return $matches
            ->map(function ($match): array {
                $stats = $match->review_status === 'confirmed'
                    ? $this->journalistStats($match->journalist, 0)
                    : null;

                return [
                    'match_id' => $match->id,
                    'journalist' => [
                        'id' => $match->journalist->id,
                        'display_name' => $match->journalist->display_name,
                        'canonical_name' => $match->journalist->canonical_name,
                    ],
                    'match_source' => $match->match_source,
                    'matched_text' => $match->matched_text,
                    'confidence' => $match->confidence,
                    'review_status' => $match->review_status,
                    'included_in_stats' => $match->review_status === 'confirmed',
                    'crowd_confirm_count' => $match->crowdVotes()->where('action', 'confirm')->count(),
                    'crowd_deny_count' => $match->crowdVotes()->where('action', 'deny')->count(),
                    'stats' => $stats ? $this->summaryOnly($stats) : null,
                ];
            })
            ->values()
            ->all();
    }

    public function statsForNewsQuery(Builder $query, int $articleLimit = 20, bool $includePeriods = false): array
    {
        $base = clone $query;
        $articleCount = (int) (clone $base)->count('news_urls.id');
        $ids = (clone $base)->pluck('news_urls.id')->map(fn ($id) => (int) $id)->all();

        $articleScores = $ids ? $this->articleScores($ids) : collect();
        $tagDistribution = $this->tagDistributionFromScores($articleScores);
        $topTag = $tagDistribution[0] ?? null;

        $recent90Count = (int) (clone $query)
            ->where('news_urls.created_at', '>=', now()->subDays(90))
            ->count('news_urls.id');

        $payload = [
            // Primary representative tag (highest vote count across articles)
            'top_tag' => $topTag,
            // Backward-compat alias
            'tracked_tag' => $topTag,
            'article_count' => $articleCount,
            'tag_distribution' => $tagDistribution,
            // Backward-compat scalar fields
            'tracked_tag_count' => $topTag['article_count'] ?? 0,
            'tracked_tag_ratio' => $topTag
                ? $this->ratioOrNull($topTag['article_count'] ?? 0, $articleCount)
                : null,
            'recent_90_days' => [
                'article_count' => $recent90Count,
                'tracked_tag_count' => 0,
                'tracked_tag_ratio' => null,
            ],
            'sample_confidence' => $this->sampleConfidence($articleCount),
            'min_sample_size' => $this->minSampleSize(),
            'ratio_available' => $articleCount >= $this->minSampleSize(),
            'consensus' => [
                'min_weight' => $this->minTagWeight(),
                'min_ratio' => $this->minTagRatio(),
            ],
            'articles' => [],
        ];

        if ($includePeriods) {
            $payload['periods'] = $this->computePeriods(clone $query);
        }

        if ($articleLimit > 0) {
            $payload['articles'] = $this->articleList(clone $query, $articleScores, $articleLimit);
        }

        return $payload;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function articleScores(array $newsUrlIds): Collection
    {
        $rows = DB::table('votes')
            ->join('tags', 'tags.id', '=', 'votes.tag_id')
            ->whereIn('votes.news_url_id', $newsUrlIds)
            ->where('votes.hidden', false)
            ->select([
                'votes.news_url_id',
                'votes.tag_id',
                'tags.slug',
                'tags.name',
                'tags.severity',
                'tags.color',
                DB::raw('sum(votes.weight_score) as weight'),
                DB::raw('count(votes.id) as vote_count'),
            ])
            ->groupBy('votes.news_url_id', 'votes.tag_id', 'tags.slug', 'tags.name', 'tags.severity', 'tags.color')
            ->get()
            ->groupBy('news_url_id');

        return collect($newsUrlIds)->mapWithKeys(function (int $newsUrlId) use ($rows): array {
            $tagRows = collect($rows->get($newsUrlId, []));
            $total = (float) $tagRows->sum('weight');
            $top = $tagRows->sortByDesc(fn ($row) => (float) $row->weight)->first();

            return [$newsUrlId => [
                'news_url_id' => $newsUrlId,
                'total_weight' => round($total, 4),
                'top_tag' => $top ? [
                    'id' => (int) $top->tag_id,
                    'slug' => $top->slug,
                    'name' => $top->name,
                    'severity' => $top->severity,
                    'color' => $top->color ?? '#67e8f9',
                    'weight' => round((float) $top->weight, 4),
                ] : null,
                'vote_count' => (int) $tagRows->sum('vote_count'),
                // Kept for articleList backward-compat; always false now
                'tracked_effective' => false,
                'tracked_weight' => 0.0,
                'tracked_ratio' => 0.0,
            ]];
        });
    }

    private function tagDistributionFromScores(Collection $scores): array
    {
        $withTag = $scores->filter(fn ($s) => $s['top_tag'] !== null);
        $total = $withTag->count();

        return $withTag
            ->groupBy(fn ($s) => $s['top_tag']['id'])
            ->map(function (Collection $group) use ($total): array {
                $tag = $group->first()['top_tag'];
                $count = $group->count();

                return [
                    'tag_id' => $tag['id'],
                    'slug' => $tag['slug'],
                    'name' => $tag['name'],
                    'severity' => $tag['severity'],
                    'color' => $tag['color'] ?? '#67e8f9',
                    'article_count' => $count,
                    'ratio' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('article_count')
            ->values()
            ->all();
    }

    private function computePeriods(Builder $query): array
    {
        $windows = [
            'all_time' => null,
            'last_90_days' => now()->subDays(90),
            'last_30_days' => now()->subDays(30),
        ];

        $result = [];
        foreach ($windows as $key => $since) {
            $q = clone $query;
            if ($since) {
                $q->where('news_urls.created_at', '>=', $since);
            }
            $ids = $q->pluck('news_urls.id')->map(fn ($id) => (int) $id)->all();
            $scores = $ids ? $this->articleScores($ids) : collect();
            $dist = $this->tagDistributionFromScores($scores);
            $result[$key] = [
                'article_count' => count($ids),
                'tag_distribution' => $dist,
            ];
        }

        return $result;
    }

    private function articleList(Builder $query, Collection $articleScores, int $limit): array
    {
        return $query
            ->with(['mediaOutlet:id,name,slug'])
            ->with(['eventItems.event:id,name,slug'])
            ->latest('news_urls.created_at')
            ->limit($limit)
            ->get(['id', 'media_outlet_id', 'normalized_url', 'title_snapshot', 'created_at', 'finalized_at'])
            ->map(function (NewsUrl $newsUrl) use ($articleScores): array {
                $score = $articleScores->get($newsUrl->id, [
                    'total_weight' => 0,
                    'tracked_weight' => 0,
                    'tracked_ratio' => 0,
                    'tracked_effective' => false,
                    'top_tag' => null,
                    'vote_count' => 0,
                ]);

                return [
                    'id' => $newsUrl->id,
                    'title_snapshot' => $newsUrl->title_snapshot,
                    'normalized_url' => $newsUrl->normalized_url,
                    'media_outlet' => $newsUrl->mediaOutlet ? [
                        'id' => $newsUrl->mediaOutlet->id,
                        'name' => $newsUrl->mediaOutlet->name,
                        'slug' => $newsUrl->mediaOutlet->slug,
                    ] : null,
                    'created_at' => $newsUrl->created_at?->toJSON(),
                    'finalized_at' => $newsUrl->finalized_at?->toJSON(),
                    'top_tag' => $score['top_tag'],
                    'total_weight' => $score['total_weight'],
                    'vote_count' => $score['vote_count'],
                    'events' => $newsUrl->eventItems
                        ->pluck('event')
                        ->filter()
                        ->map(fn ($event) => ['id' => $event->id, 'name' => $event->name, 'slug' => $event->slug])
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    private function summaryOnly(array $stats): array
    {
        unset($stats['articles'], $stats['periods']);

        return $stats;
    }

    private function ratioOrNull(int $count, int $total): ?float
    {
        if ($total < $this->minSampleSize() || $total <= 0) {
            return null;
        }

        return round(($count / $total) * 100, 2);
    }

    private function sampleConfidence(int $count): string
    {
        return match (true) {
            $count < $this->minSampleSize() => 'insufficient',
            $count < 30 => 'low',
            $count < 100 => 'medium',
            default => 'high',
        };
    }

    private function minSampleSize(): int
    {
        return (int) config('truthshield.report_stats.min_sample_size', 10);
    }

    private function minTagWeight(): float
    {
        return (float) config('truthshield.report_stats.min_tag_weight', 1.0);
    }

    private function minTagRatio(): float
    {
        return (float) config('truthshield.report_stats.min_tag_ratio', 0.5);
    }
}
