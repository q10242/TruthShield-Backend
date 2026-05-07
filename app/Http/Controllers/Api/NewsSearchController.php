<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'finalized' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = NewsUrl::query()
            ->with('mediaOutlet:id,name,slug')
            ->withCount('votes')
            ->latest();

        if (! empty($validated['q'])) {
            $term = '%' . $validated['q'] . '%';
            $query->where(fn ($builder) => $builder
                ->where('normalized_url', 'like', $term)
                ->orWhere('title_snapshot', 'like', $term)
                ->orWhereHas('officialResponses', fn ($responses) => $responses
                    ->where('status', 'published')
                    ->where('response_text', 'like', $term)));
        }

        if (! empty($validated['domain'])) {
            $query->where('normalized_url', 'like', '%://' . $validated['domain'] . '/%');
        }

        if (array_key_exists('finalized', $validated)) {
            $validated['finalized']
                ? $query->whereNotNull('finalized_at')
                : $query->whereNull('finalized_at');
        }

        $limit = (int) ($validated['limit'] ?? 30);
        $total = (clone $query)->count();

        return response()->json([
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'filters' => [
                    'q' => $validated['q'] ?? null,
                    'domain' => $validated['domain'] ?? null,
                    'finalized' => $validated['finalized'] ?? null,
                ],
            ],
            'data' => $query->limit($limit)->get([
                'id',
                'media_outlet_id',
                'hash',
                'normalized_url',
                'title_snapshot',
                'voting_closes_at',
                'finalized_at',
                'created_at',
            ]),
        ]);
    }
}
