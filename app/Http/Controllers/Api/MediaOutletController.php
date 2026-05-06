<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaOutlet;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MediaOutletController extends Controller
{
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
            'tag_weights' => $weights,
            'recent_news' => $mediaOutlet->newsUrls()
                ->withCount('votes')
                ->latest()
                ->limit(20)
                ->get(['id', 'media_outlet_id', 'normalized_url', 'title_snapshot', 'finalized_at', 'created_at']),
        ]);
    }
}
