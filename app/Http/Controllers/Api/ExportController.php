<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunitySignal;
use App\Models\BugReport;
use App\Models\Donation;
use App\Models\MediaOutlet;
use App\Models\ModerationEvent;
use App\Models\NewsChangeReport;
use App\Models\NewsUrl;
use App\Models\NewsUrlSnapshot;
use App\Models\UserDataRequest;
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

    public function donationsCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'trade_no', 'amount', 'status', 'donor_name', 'donor_email', 'paid_at', 'created_at']);

            Donation::query()
                ->latest()
                ->chunk(200, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->merchant_trade_no,
                            $row->amount,
                            $row->status,
                            $row->donor_name,
                            $row->donor_email,
                            $row->paid_at?->toJSON(),
                            $row->created_at?->toJSON(),
                        ]);
                    }
                });
        }, 'truthshield-donations.csv', ['Content-Type' => 'text/csv']);
    }

    public function userDataRequestsCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'email', 'request_type', 'status', 'created_at', 'reviewed_at']);

            UserDataRequest::query()
                ->latest()
                ->chunk(200, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->email,
                            $row->request_type,
                            $row->status,
                            $row->created_at?->toJSON(),
                            $row->reviewed_at?->toJSON(),
                        ]);
                    }
                });
        }, 'truthshield-user-data-requests.csv', ['Content-Type' => 'text/csv']);
    }

    public function snapshotsCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'news_url_id', 'news_url', 'title', 'snapshot_type', 'availability_status', 'archive_url', 'change_count', 'captured_at']);

            NewsUrlSnapshot::query()
                ->with('newsUrl:id,normalized_url')
                ->latest('captured_at')
                ->chunk(200, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->news_url_id,
                            $row->newsUrl?->normalized_url,
                            $row->title,
                            $row->snapshot_type,
                            $row->availability_status,
                            $row->archive_url,
                            count($row->change_summary ?: []),
                            $row->captured_at?->toJSON(),
                        ]);
                    }
                });
        }, 'truthshield-news-snapshots.csv', ['Content-Type' => 'text/csv']);
    }

    public function changeReportsCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'news_url_id', 'report_type', 'status', 'url', 'page_title', 'note', 'created_at', 'reviewed_at']);

            NewsChangeReport::query()
                ->latest()
                ->chunk(200, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->news_url_id,
                            $row->report_type,
                            $row->status,
                            $row->url,
                            $row->page_title,
                            $row->note,
                            $row->created_at?->toJSON(),
                            $row->reviewed_at?->toJSON(),
                        ]);
                    }
                });
        }, 'truthshield-news-change-reports.csv', ['Content-Type' => 'text/csv']);
    }

    public function governanceCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'event_type', 'subject_type', 'subject_id', 'public_reason', 'created_at']);

            ModerationEvent::query()
                ->latest()
                ->chunk(200, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->event_type,
                            $row->subject_type,
                            $row->subject_id,
                            $row->public_reason,
                            $row->created_at?->toJSON(),
                        ]);
                    }
                });
        }, 'truthshield-governance-events.csv', ['Content-Type' => 'text/csv']);
    }

    public function communitySignalsCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'signal_type', 'subject_type', 'subject_id', 'subject_key', 'value', 'weight_score', 'authenticated', 'created_at']);

            CommunitySignal::query()
                ->latest()
                ->chunk(500, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->signal_type,
                            $row->subject_type,
                            $row->subject_id,
                            $row->subject_key,
                            $row->value,
                            $row->weight_score,
                            $row->user_id ? 1 : 0,
                            $row->created_at?->toJSON(),
                        ]);
                    }
                });
        }, 'truthshield-community-signals.csv', ['Content-Type' => 'text/csv']);
    }

    public function bugReportsCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'report_type', 'severity', 'status', 'title', 'source', 'page_url', 'contact_email', 'extension_version', 'created_at', 'reviewed_at']);

            BugReport::query()
                ->latest()
                ->chunk(200, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->report_type,
                            $row->severity,
                            $row->status,
                            $row->title,
                            $row->source,
                            $row->page_url,
                            $row->contact_email,
                            $row->extension_version,
                            $row->created_at?->toJSON(),
                            $row->reviewed_at?->toJSON(),
                        ]);
                    }
                });
        }, 'truthshield-bug-reports.csv', ['Content-Type' => 'text/csv']);
    }
}
