<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaOutlet;
use App\Models\Vote;
use App\Services\ReportLabelStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MediaOutletController extends Controller
{
    public function index(Request $request, ReportLabelStatsService $stats): JsonResponse
    {
        $query = MediaOutlet::query()
            ->withCount('newsUrls')
            ->where('is_active', true);

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%"));
        }

        $outlets = $query
            ->orderByDesc('news_urls_count')
            ->orderBy('name')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 30))));

        $outlets->getCollection()->transform(fn (MediaOutlet $outlet) => [
            'id' => $outlet->id,
            'name' => $outlet->name,
            'slug' => $outlet->slug,
            'type' => $outlet->type,
            'region' => $outlet->region,
            'news_urls_count' => $outlet->news_urls_count,
            'stats' => $stats->mediaStats($outlet, 0),
            'updated_at' => $outlet->updated_at?->toJSON(),
        ]);

        return response()->json($outlets);
    }

    public function aggregateStats(Request $request, ReportLabelStatsService $stats): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('per_page', 30)));

        $outlets = MediaOutlet::query()
            ->withCount('newsUrls')
            ->where('is_active', true)
            ->orderByDesc('news_urls_count')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (MediaOutlet $outlet) => [
                'id' => $outlet->id,
                'name' => $outlet->name,
                'slug' => $outlet->slug,
                'type' => $outlet->type,
                'region' => $outlet->region,
                'news_urls_count' => $outlet->news_urls_count,
                'stats' => $stats->mediaStats($outlet, 0),
                'updated_at' => $outlet->updated_at?->toJSON(),
            ])
            ->values();

        return response()->json([
            'data' => $outlets,
            'meta' => [
                'per_page' => $limit,
                'total' => MediaOutlet::query()->where('is_active', true)->count(),
                'tracked_tag' => ReportLabelStatsService::TRACKED_TAG_SLUG,
            ],
        ]);
    }

    public function show(MediaOutlet $mediaOutlet): JsonResponse
    {
        $weights = Vote::query()
            ->join('news_urls', 'news_urls.id', '=', 'votes.news_url_id')
            ->join('tags', 'tags.id', '=', 'votes.tag_id')
            ->where('news_urls.media_outlet_id', $mediaOutlet->id)
            ->select('tags.slug', 'tags.name', 'tags.severity', DB::raw('sum(votes.weight_score) as weight'))
            ->groupBy('tags.slug', 'tags.name', 'tags.severity')
            ->orderByDesc('weight')
            ->get();

        return response()->json([
            'media' => $mediaOutlet->load('domains:id,media_outlet_id,domain,is_active'),
            'official_response_count' => $mediaOutlet->newsUrls()
                ->whereHas('officialResponses', fn ($query) => $query->where('status', 'published'))
                ->withCount(['officialResponses as published_official_responses_count' => fn ($query) => $query->where('status', 'published')])
                ->get()
                ->sum('published_official_responses_count'),
            'tag_weights' => $weights,
            'recent_news' => $mediaOutlet->newsUrls()
                ->withCount('votes')
                ->latest()
                ->limit(20)
                ->get(['id', 'media_outlet_id', 'normalized_url', 'title_snapshot', 'finalized_at', 'created_at']),
        ]);
    }

    public function stats(MediaOutlet $mediaOutlet, ReportLabelStatsService $stats): JsonResponse
    {
        return response()->json([
            'media' => $mediaOutlet->only(['id', 'name', 'slug', 'type', 'region']),
            'data' => $stats->mediaStats($mediaOutlet, 50),
        ]);
    }
}
