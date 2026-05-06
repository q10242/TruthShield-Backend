<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaOutlet;
use App\Models\NewsUrl;
use App\Models\Vote;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function mediaCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'name', 'slug', 'type', 'region', 'active', 'domains', 'tracked_urls']);

            MediaOutlet::query()
                ->withCount(['domains', 'newsUrls'])
                ->orderBy('name')
                ->chunk(200, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->name,
                            $row->slug,
                            $row->type,
                            $row->region,
                            $row->is_active ? 1 : 0,
                            $row->domains_count,
                            $row->news_urls_count,
                        ]);
                    }
                });
        }, 'truthshield-media.csv', ['Content-Type' => 'text/csv']);
    }

    public function newsCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'finalized' => ['nullable', 'boolean'],
        ]);

        return response()->streamDownload(function () use ($validated): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'media', 'hash', 'normalized_url', 'title', 'votes', 'voting_closes_at', 'finalized_at', 'created_at']);

            $query = NewsUrl::query()
                ->with('mediaOutlet:id,name')
                ->withCount('votes')
                ->latest();

            if (array_key_exists('finalized', $validated)) {
                $validated['finalized']
                    ? $query->whereNotNull('finalized_at')
                    : $query->whereNull('finalized_at');
            }

            $query->chunk(200, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->mediaOutlet?->name,
                        $row->hash,
                        $row->normalized_url,
                        $row->title_snapshot,
                        $row->votes_count,
                        $row->voting_closes_at?->toJSON(),
                        $row->finalized_at?->toJSON(),
                        $row->created_at?->toJSON(),
                    ]);
                }
            });
        }, 'truthshield-news.csv', ['Content-Type' => 'text/csv']);
    }

    public function evidenceCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'trusted' => ['nullable', 'boolean'],
        ]);

        return response()->streamDownload(function () use ($validated): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'news_url', 'tag', 'evidence_url', 'evidence_host', 'evidence_safety', 'note', 'weight_score', 'hidden', 'created_at']);

            $query = Vote::query()
                ->with(['newsUrl:id,normalized_url', 'tag:id,name'])
                ->whereNotNull('evidence_url')
                ->latest();

            if (array_key_exists('trusted', $validated)) {
                $query->where('evidence_safety', $validated['trusted'] ? 'trusted' : 'unverified');
            }

            $query->chunk(200, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->newsUrl?->normalized_url,
                        $row->tag?->name,
                        $row->evidence_url,
                        $row->evidence_host,
                        $row->evidence_safety,
                        $row->evidence_note,
                        $row->weight_score,
                        $row->hidden ? 1 : 0,
                        $row->created_at?->toJSON(),
                    ]);
                }
            });
        }, 'truthshield-evidence.csv', ['Content-Type' => 'text/csv']);
    }
}
