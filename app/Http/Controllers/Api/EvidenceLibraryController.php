<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvidenceLibraryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:80'],
            'domain' => ['nullable', 'string', 'max:255'],
            'trusted' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Vote::query()
            ->where('hidden', false)
            ->whereNotNull('evidence_url')
            ->with(['tag:id,name,slug,color,severity', 'newsUrl:id,normalized_url,title_snapshot,media_outlet_id', 'newsUrl.mediaOutlet:id,name,slug'])
            ->withSum(['reactions as helpful_weight' => fn ($query) => $query->where('helpful', true)], 'weight_score')
            ->withSum(['reactions as unhelpful_weight' => fn ($query) => $query->where('helpful', false)], 'weight_score')
            ->latest();

        if (! empty($validated['q'])) {
            $term = '%' . $validated['q'] . '%';
            $query->where(fn ($builder) => $builder
                ->where('evidence_note', 'like', $term)
                ->orWhere('evidence_url', 'like', $term)
                ->orWhereHas('newsUrl', fn ($urlQuery) => $urlQuery
                    ->where('title_snapshot', 'like', $term)
                    ->orWhere('normalized_url', 'like', $term)));
        }

        if (! empty($validated['tag'])) {
            $query->whereHas('tag', fn ($tagQuery) => $tagQuery->where('slug', $validated['tag']));
        }

        if (! empty($validated['domain'])) {
            $query->whereHas('newsUrl', fn ($urlQuery) => $urlQuery->where('normalized_url', 'like', '%://' . $validated['domain'] . '/%'));
        }

        if (array_key_exists('trusted', $validated)) {
            $query->where('evidence_safety', $validated['trusted'] ? 'trusted' : 'unverified');
        }

        $limit = (int) ($validated['limit'] ?? 50);
        $total = (clone $query)->count();
        $rows = $query->limit($limit)->get();

        return response()->json([
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'filters' => [
                    'q' => $validated['q'] ?? null,
                    'tag' => $validated['tag'] ?? null,
                    'domain' => $validated['domain'] ?? null,
                    'trusted' => $validated['trusted'] ?? null,
                ],
            ],
            'data' => $rows->map(fn (Vote $vote) => [
                'id' => $vote->id,
                'tag' => $vote->tag,
                'news_url' => $vote->newsUrl,
                'evidence_url' => $vote->evidence_url,
                'evidence_type' => $vote->evidence_type,
                'evidence_host' => $vote->evidence_host,
                'evidence_safety' => $vote->evidence_safety,
                'is_trusted_evidence' => $vote->evidence_safety === 'trusted',
                'evidence_note' => $vote->evidence_note,
                'helpful_weight' => round((float) ($vote->helpful_weight ?? 0), 4),
                'unhelpful_weight' => round((float) ($vote->unhelpful_weight ?? 0), 4),
                'net_helpful_weight' => round((float) ($vote->helpful_weight ?? 0) - (float) ($vote->unhelpful_weight ?? 0), 4),
            ]),
        ]);
    }
}
