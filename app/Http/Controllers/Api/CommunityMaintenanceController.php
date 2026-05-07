<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrustedSourceSuggestion;
use App\Models\UrlClassificationReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommunityMaintenanceController extends Controller
{
    public function storeUrlClassification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
            'classification' => ['required', 'string', 'in:article,list,home,search,not_news,unknown'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $host = strtolower((string) parse_url($validated['url'], PHP_URL_HOST));

        if (! $host) {
            return response()->json([
                'message' => 'Unable to parse domain from URL.',
                'errors' => ['url' => ['Unable to parse domain from URL.']],
            ], 422);
        }

        $user = $request->user();
        $weight = max(0.1, (float) ($user?->trust_score ?? 0.25));
        $pathSignature = $this->pathSignature($validated['url']);
        $classification = $validated['classification'];

        $report = UrlClassificationReport::query()
            ->where('domain', $host)
            ->where('path_signature', $pathSignature)
            ->where('classification', $classification)
            ->where('status', 'pending')
            ->first();

        $data = [
            'user_id' => $user?->id,
            'url' => $validated['url'],
            'page_title' => $validated['page_title'] ?? null,
            'note' => $validated['note'] ?? null,
            'suggested_pattern' => $this->suggestedPattern($validated['url'], $classification),
            'last_reported_at' => now(),
        ];

        if ($report) {
            $report->forceFill([
                ...$data,
                'report_count' => $report->report_count + 1,
                'weighted_score' => round($report->weighted_score + $weight, 4),
            ])->save();
        } else {
            $report = UrlClassificationReport::query()->create([
                ...$data,
                'domain' => $host,
                'path_signature' => $pathSignature,
                'classification' => $classification,
                'status' => 'pending',
                'report_count' => 1,
                'weighted_score' => round($weight, 4),
            ]);
        }

        return response()->json([
            'message' => 'URL classification report received.',
            'report' => $report,
        ], 201);
    }

    public function storeTrustedSourceSuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['nullable', 'url', 'max:2048'],
            'host' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $host = strtolower((string) ($validated['host'] ?? ''));
        if (! $host && ! empty($validated['url'])) {
            $host = strtolower((string) parse_url($validated['url'], PHP_URL_HOST));
        }

        if (! $host) {
            return response()->json([
                'message' => 'host or url is required.',
                'errors' => ['host' => ['host or url is required.']],
            ], 422);
        }

        $host = preg_replace('/^www\./', '', $host) ?: $host;
        $sourceType = $validated['source_type'] ?? 'cloud_drive';
        $user = $request->user();
        $weight = max(0.1, (float) ($user?->trust_score ?? 0.25));

        $suggestion = TrustedSourceSuggestion::query()
            ->where('host', $host)
            ->where('source_type', $sourceType)
            ->where('status', 'pending')
            ->first();

        $data = [
            'user_id' => $user?->id,
            'example_url' => $validated['url'] ?? null,
            'note' => $validated['note'] ?? null,
            'last_reported_at' => now(),
        ];

        if ($suggestion) {
            $suggestion->forceFill([
                ...$data,
                'report_count' => $suggestion->report_count + 1,
                'weighted_score' => round($suggestion->weighted_score + $weight, 4),
            ])->save();
        } else {
            $suggestion = TrustedSourceSuggestion::query()->create([
                ...$data,
                'host' => $host,
                'source_type' => $sourceType,
                'status' => 'pending',
                'report_count' => 1,
                'weighted_score' => round($weight, 4),
            ]);
        }

        return response()->json([
            'message' => 'Trusted source suggestion received.',
            'suggestion' => $suggestion,
        ], 201);
    }

    private function pathSignature(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === '') {
            return '/';
        }

        return collect(explode('/', $path))
            ->map(function (string $part): string {
                if (preg_match('/^\d{4,}$/', $part)) {
                    return '{id}';
                }

                if (preg_match('/\d{6,}/', $part)) {
                    return preg_replace('/\d{6,}/', '{id}', $part);
                }

                return Str::limit($part, 80, '');
            })
            ->implode('/');
    }

    private function suggestedPattern(string $url, string $classification): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($path === '' || $path === '/') {
            return '^/$';
        }

        $pattern = preg_quote($path, '/');
        $pattern = preg_replace('/\\\\\d{4,}/', '\\\\d+', $pattern);
        $pattern = preg_replace('/\d{6,}/', '\\\\d+', $pattern);

        return in_array($classification, ['article', 'list', 'home', 'search'], true)
            ? "^{$pattern}$"
            : null;
    }
}
