<?php

namespace App\Jobs;

use App\Models\Evidence;
use App\Models\EvidenceSnapshot;
use App\Services\EvidenceUrlService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SnapshotEvidenceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $evidenceId) {}

    public int $tries = 3;
    public int $timeout = 30;

    public function handle(EvidenceUrlService $evidenceUrls): void
    {
        $evidence = Evidence::query()->find($this->evidenceId);
        if (! $evidence) {
            return;
        }

        $metadata = ['host' => $evidence->host, 'type' => $evidence->type];
        $status = 'failed';
        $previewUrl = $evidence->preview_url;

        try {
            $evidenceUrls->assertFetchableUrl($evidence->url);

            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withHeaders(['User-Agent' => 'TruthShieldBot/0.1'])
                ->head($evidence->url);

            if (! $response->successful()) {
                $response = Http::timeout(8)
                    ->connectTimeout(3)
                    ->withHeaders(['User-Agent' => 'TruthShieldBot/0.1'])
                    ->get($evidence->url);
            }

            $contentType = $response->header('content-type');
            $contentLength = (int) ($response->header('content-length') ?: 0);
            $finalUrl = (string) $response->effectiveUri();
            if ($finalUrl) {
                $evidenceUrls->assertFetchableUrl($finalUrl);
            }

            if (! $evidenceUrls->isAllowedContentType($contentType)) {
                throw new \RuntimeException('Evidence content type is not allowed.');
            }

            if ($contentLength > (int) config('truthshield.evidence_snapshot_max_bytes', 5_242_880)) {
                throw new \RuntimeException('Evidence content is too large.');
            }

            $metadata += [
                'http_status' => $response->status(),
                'content_type' => $contentType,
                'content_length' => $contentLength ?: null,
                'final_url' => $finalUrl,
            ];

            $status = $response->successful() ? 'snapshotted' : 'failed';
            if ($evidence->type === 'image' && ! $previewUrl && $response->successful()) {
                $previewUrl = $evidence->url;
            }
        } catch (\Throwable $exception) {
            $metadata['error'] = $exception->getMessage();
        }

        $snapshot = EvidenceSnapshot::query()->create([
            'evidence_id' => $evidence->id,
            'status' => $status,
            'archive_url' => $evidence->archive_url,
            'preview_url' => $previewUrl,
            'attempts' => 1,
            'last_attempted_at' => now(),
            'metadata' => $metadata,
        ]);

        $evidence->forceFill([
            'snapshot_status' => $snapshot->status,
            'preview_url' => $previewUrl,
            'metadata' => array_merge($evidence->metadata ?? [], ['last_snapshot' => $metadata]),
        ])->save();
    }
}
