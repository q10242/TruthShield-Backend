<?php

namespace App\Services;

use App\Models\NewsUrl;
use App\Models\NewsUrlSnapshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class NewsSnapshotService
{
    public function capture(NewsUrl $newsUrl, array $metadata, string $snapshotType = 'observed'): NewsUrlSnapshot
    {
        $payload = $this->normalizeMetadata($metadata);
        $latest = $newsUrl->snapshots()->latest('captured_at')->first();
        $changes = $this->changesFrom($latest, $payload);

        $snapshot = $newsUrl->snapshots()->create([
            'title' => $payload['title'],
            'canonical_url' => $payload['canonical_url'],
            'description' => $payload['description'],
            'image_url' => $payload['image_url'],
            'content_hash' => $payload['content_hash'],
            'snapshot_type' => $latest ? ($changes ? 'changed' : $snapshotType) : 'initial',
            'availability_status' => $payload['availability_status'],
            'archive_url' => $payload['archive_url'],
            'change_summary' => $changes,
            'metadata' => Arr::only($payload, ['source', 'user_agent']),
            'captured_at' => now(),
        ]);

        $newsUrl->forceFill([
            'canonical_url' => $payload['canonical_url'] ?: $newsUrl->canonical_url,
            'title_snapshot' => $newsUrl->title_snapshot ?: $payload['title'],
            'description_snapshot' => $payload['description'] ?: $newsUrl->description_snapshot,
            'image_snapshot_url' => $payload['image_url'] ?: $newsUrl->image_snapshot_url,
            'content_hash' => $payload['content_hash'] ?: $newsUrl->content_hash,
            'availability_status' => $payload['availability_status'],
            'last_snapshot_at' => $snapshot->captured_at,
            'archive_url' => $payload['archive_url'] ?: $newsUrl->archive_url,
        ])->save();

        return $snapshot;
    }

    public function statusPayload(NewsUrl $newsUrl): array
    {
        $latest = $newsUrl->snapshots()->latest('captured_at')->first();
        $changedCount = $newsUrl->snapshots()->where('snapshot_type', 'changed')->count();
        $pendingReports = $newsUrl->changeReports()->where('status', 'pending')->count();

        return [
            'availability_status' => $newsUrl->availability_status ?: 'available',
            'last_snapshot_at' => $newsUrl->last_snapshot_at?->toJSON(),
            'archive_url' => $newsUrl->archive_url,
            'snapshots_count' => $newsUrl->snapshots()->count(),
            'changed_snapshots_count' => $changedCount,
            'pending_change_reports_count' => $pendingReports,
            'latest_snapshot' => $latest ? [
                'id' => $latest->id,
                'title' => $latest->title,
                'canonical_url' => $latest->canonical_url,
                'availability_status' => $latest->availability_status,
                'snapshot_type' => $latest->snapshot_type,
                'change_summary' => $latest->change_summary ?: [],
                'captured_at' => $latest->captured_at?->toJSON(),
                'archive_url' => $latest->archive_url,
            ] : null,
        ];
    }

    private function normalizeMetadata(array $metadata): array
    {
        $contentHash = $metadata['content_hash'] ?? null;

        return [
            'title' => Str::limit(trim((string) ($metadata['title_snapshot'] ?? $metadata['title'] ?? '')), 255, ''),
            'canonical_url' => trim((string) ($metadata['canonical_url'] ?? '')) ?: null,
            'description' => Str::limit(trim((string) ($metadata['description'] ?? '')), 500, ''),
            'image_url' => trim((string) ($metadata['image_url'] ?? '')) ?: null,
            'content_hash' => $contentHash ? strtolower(substr((string) $contentHash, 0, 64)) : null,
            'availability_status' => $metadata['availability_status'] ?? 'available',
            'archive_url' => trim((string) ($metadata['archive_url'] ?? '')) ?: null,
            'source' => $metadata['source'] ?? 'extension',
            'user_agent' => $metadata['user_agent'] ?? null,
        ];
    }

    private function changesFrom(?NewsUrlSnapshot $latest, array $payload): array
    {
        if (! $latest) {
            return [];
        }

        $changes = [];

        foreach ([
            'title' => 'title_changed',
            'canonical_url' => 'canonical_changed',
            'description' => 'description_changed',
            'image_url' => 'image_changed',
            'content_hash' => 'content_changed',
            'availability_status' => 'availability_changed',
        ] as $field => $changeType) {
            $old = (string) ($latest->{$field} ?? '');
            $new = (string) ($payload[$field] ?? '');

            if ($new !== '' && $old !== '' && $new !== $old) {
                $changes[] = [
                    'type' => $changeType,
                    'from' => Str::limit($old, 220, ''),
                    'to' => Str::limit($new, 220, ''),
                ];
            }
        }

        return $changes;
    }
}
