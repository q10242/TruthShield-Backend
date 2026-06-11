<?php

namespace App\Services;

use App\Models\Journalist;
use App\Models\MediaOutlet;
use App\Models\NewsUrl;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportLabelStatsService
{
    public const TRACKED_TAG_SLUG = 'clickbait-title';

    public function mediaStats(MediaOutlet $mediaOutlet, int $articleLimit = 20): array
    {
        return $this->statsForNewsQuery(
            NewsUrl::query()->where('media_outlet_id', $mediaOutlet->id),
            $articleLimit,
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
                    'stats' => $stats ? $this->summaryOnly($stats) : null,
                ];
            })
            ->values()
            ->all();
    }

    public function statsForNewsQuery(Builder $query, int $articleLimit = 20): array
    {
        $base = clone $query;
        $articleCount = (int) (clone $base)->count('news_urls.id');
        $ids = (clone $base)->pluck('news_urls.id')->map(fn ($id) => (int) $id)->all();
        $trackedTag = Tag::query()
            ->where('slug', self::TRACKED_TAG_SLUG)
            ->first(['id', 'name', 'slug', 'severity', 'color', 'translations']);

        $articleScores = $trackedTag && $ids
            ? $this->articleScores($ids, (int) $trackedTag->id)
            : collect();
        $clickbaitCount = $articleScores->filter(fn (array $row) => $row['tracked_effective'])->count();
        $recentIds = (clone $query)
            ->where('news_urls.created_at', '>=', now()->subDays(90))
            ->pluck('news_urls.id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $recentScores = $trackedTag && $recentIds
            ? $this->articleScores($recentIds, (int) $trackedTag->id)
            : collect();
        $recentCount = count($recentIds);
        $recentClickbaitCount = $recentScores->filter(fn (array $row) => $row['tracked_effective'])->count();

        $payload = [
            'tracked_tag' => $trackedTag ? [
                'id' => $trackedTag->id,
                'slug' => $trackedTag->slug,
                'name' => $trackedTag->name,
                'severity' => $trackedTag->severity,
                'color' => $trackedTag->color,
            ] : null,
            'article_count' => $articleCount,
            'tracked_tag_count' => $clickbaitCount,
            'tracked_tag_ratio' => $this->ratioOrNull($clickbaitCount, $articleCount),
            'recent_90_days' => [
                'article_count' => $recentCount,
                'tracked_tag_count' => $recentClickbaitCount,
                'tracked_tag_ratio' => $this->ratioOrNull($recentClickbaitCount, $recentCount),
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

        if ($articleLimit > 0) {
            $payload['articles'] = $this->articleList((clone $query), $articleScores, $articleLimit);
        }

        return $payload;
    }

    private function articleScores(array $newsUrlIds, int $trackedTagId): Collection
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
                DB::raw('sum(votes.weight_score) as weight'),
                DB::raw('count(votes.id) as vote_count'),
            ])
            ->groupBy('votes.news_url_id', 'votes.tag_id', 'tags.slug', 'tags.name', 'tags.severity')
            ->get()
            ->groupBy('news_url_id');

        return collect($newsUrlIds)->mapWithKeys(function (int $newsUrlId) use ($rows, $trackedTagId): array {
            $tagRows = collect($rows->get($newsUrlId, []));
            $total = (float) $tagRows->sum('weight');
            $top = $tagRows->sortByDesc(fn ($row) => (float) $row->weight)->first();
            $tracked = $tagRows->firstWhere('tag_id', $trackedTagId);
            $trackedWeight = (float) ($tracked->weight ?? 0);
            $trackedRatio = $total > 0 ? $trackedWeight / $total : 0.0;

            return [$newsUrlId => [
                'news_url_id' => $newsUrlId,
                'total_weight' => round($total, 4),
                'tracked_weight' => round($trackedWeight, 4),
                'tracked_ratio' => round($trackedRatio * 100, 2),
                'tracked_effective' => $trackedWeight >= $this->minTagWeight() && $trackedRatio >= $this->minTagRatio(),
                'top_tag' => $top ? [
                    'id' => (int) $top->tag_id,
                    'slug' => $top->slug,
                    'name' => $top->name,
                    'severity' => $top->severity,
                    'weight' => round((float) $top->weight, 4),
                ] : null,
                'vote_count' => (int) $tagRows->sum('vote_count'),
            ]];
        });
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
                    'tracked_tag_weight' => $score['tracked_weight'],
                    'tracked_tag_ratio' => $score['tracked_ratio'],
                    'tracked_tag_effective' => $score['tracked_effective'],
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
        unset($stats['articles']);

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
