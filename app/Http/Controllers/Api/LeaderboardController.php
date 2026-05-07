<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaOutlet;
use App\Models\NewsDomain;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function media(): JsonResponse
    {
        $rows = Cache::store(config('truthshield.status_cache_store'))->remember('leaderboard:media:v1', now()->addSeconds(30), function () {
            $weightsByOutlet = Vote::query()
                ->join('news_urls', 'news_urls.id', '=', 'votes.news_url_id')
                ->join('tags', 'tags.id', '=', 'votes.tag_id')
                ->whereNotNull('news_urls.media_outlet_id')
                ->select('news_urls.media_outlet_id', 'tags.severity', DB::raw('sum(votes.weight_score) as weight'))
                ->groupBy('news_urls.media_outlet_id', 'tags.severity')
                ->get()
                ->groupBy('media_outlet_id');

            return MediaOutlet::query()
                ->withCount('newsUrls')
                ->get()
                ->map(function (MediaOutlet $outlet) use ($weightsByOutlet) {
                    $weights = $weightsByOutlet
                        ->get($outlet->id, collect())
                        ->pluck('weight', 'severity');

                    $positive = (float) ($weights['positive'] ?? 0);
                    $negative = (float) $weights->except('positive')->sum();
                    $total = $positive + $negative;
                    $score = $total > 0 ? round(($positive / $total) * 100, 2) : 50.0;

                    return [
                        'id' => $outlet->id,
                        'name' => $outlet->name,
                        'slug' => $outlet->slug,
                        'type' => $outlet->type,
                        'region' => $outlet->region,
                        'score' => $score,
                        'risk' => $score >= 75 ? 'low' : ($score >= 45 ? 'medium' : 'high'),
                        'positive_weight' => round($positive, 4),
                        'negative_weight' => round($negative, 4),
                        'tracked_urls' => $outlet->news_urls_count,
                    ];
                })
                ->sortByDesc('score')
                ->values();
        });

        return response()->json(['data' => $rows]);
    }

    public function domains(): JsonResponse
    {
        return response()->json([
            'data' => NewsDomain::query()
                ->with('mediaOutlet:id,name,slug')
                ->orderBy('domain')
                ->get(['id', 'media_outlet_id', 'domain', 'name', 'is_active']),
        ]);
    }
}
