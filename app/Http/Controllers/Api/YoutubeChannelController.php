<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\YoutubeChannel;
use App\Models\YoutubeChannelReport;
use App\Services\BotProtectionService;
use App\Services\CommunitySignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class YoutubeChannelController extends Controller
{
    private const CHANNEL_TYPES = ['news', 'politics', 'official', 'fact_check', 'commentary', 'other'];

    public function index(): JsonResponse
    {
        $channels = YoutubeChannel::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('title')
            ->limit(500)
            ->get()
            ->map(fn (YoutubeChannel $channel): array => $this->channelPayload($channel))
            ->values();

        return response()->json(['data' => $channels]);
    }

    public function report(Request $request, CommunitySignalService $signals, BotProtectionService $botProtection): JsonResponse
    {
        if ($response = $botProtection->enforce($request, 'youtube_channel.report')) {
            return $response;
        }

        $validated = $request->validate([
            'channel_url' => ['required', 'url', 'max:2048'],
            'channel_title' => ['nullable', 'string', 'max:255'],
            'channel_type' => ['nullable', 'string', 'in:' . implode(',', self::CHANNEL_TYPES)],
            'note' => ['nullable', 'string', 'max:500'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $parsed = $this->parseYoutubeChannelUrl($validated['channel_url']);
        if (! $parsed['is_youtube']) {
            return response()->json([
                'message' => 'Only YouTube channel or video URLs are supported.',
                'errors' => ['channel_url' => ['Only YouTube channel or video URLs are supported.']],
            ], 422);
        }

        $channelType = $validated['channel_type'] ?? 'news';
        $user = $signals->optionalUser($request);
        $weight = $this->weightFor($user);

        $report = $this->existingPendingReport($parsed['channel_id'], $parsed['handle'], $validated['channel_url']);
        $data = [
            'user_id' => $user?->id,
            'channel_url' => $validated['channel_url'],
            'channel_title' => $validated['channel_title'] ?? null,
            'channel_type' => $channelType,
            'note' => $validated['note'] ?? null,
            'last_reported_at' => now(),
        ];

        if ($report) {
            $report->forceFill([
                ...$data,
                'report_count' => $report->report_count + 1,
                'weighted_score' => round($report->weighted_score + $weight, 4),
            ])->save();
        } else {
            $report = YoutubeChannelReport::query()->create([
                ...$data,
                'channel_id' => $parsed['channel_id'],
                'handle' => $parsed['handle'],
                'status' => 'pending',
                'report_count' => 1,
                'weighted_score' => round($weight, 4),
            ]);
        }

        $signals->record(
            $request,
            'youtube_channel_report',
            $report,
            $this->subjectKey($parsed['channel_id'], $parsed['handle'], $validated['channel_url']),
            $channelType,
            [
                'channel_id' => $parsed['channel_id'],
                'handle' => $parsed['handle'],
                'channel_url' => $validated['channel_url'],
            ],
        );

        return response()->json([
            'message' => 'YouTube channel report received.',
            'report' => $this->reportPayload($report),
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_url' => ['nullable', 'url', 'max:2048'],
            'channel_id' => ['nullable', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255'],
        ]);

        $parsed = ! empty($validated['channel_url'])
            ? $this->parseYoutubeChannelUrl($validated['channel_url'])
            : ['channel_id' => $validated['channel_id'] ?? null, 'handle' => $this->normalizeHandle($validated['handle'] ?? null)];

        if (empty($validated['channel_url']) && empty($parsed['channel_id']) && empty($parsed['handle'])) {
            return response()->json([
                'message' => 'channel_url, channel_id, or handle is required.',
                'errors' => ['channel_url' => ['channel_url, channel_id, or handle is required.']],
            ], 422);
        }

        $channel = $this->findChannel($parsed['channel_id'], $parsed['handle'], $validated['channel_url'] ?? null);
        $report = $this->findLatestReport($parsed['channel_id'], $parsed['handle'], $validated['channel_url'] ?? null);

        return response()->json([
            'is_active' => (bool) $channel?->is_active,
            'is_reported' => (bool) $report,
            'channel' => $channel ? $this->channelPayload($channel) : null,
            'report' => $report ? $this->reportPayload($report) : null,
        ]);
    }

    private function parseYoutubeChannelUrl(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: $host;
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = array_values(array_filter(explode('/', $path), fn (string $part): bool => $part !== ''));
        $first = $segments[0] ?? '';
        $second = $segments[1] ?? null;

        $isYoutube = in_array($host, ['youtube.com', 'm.youtube.com', 'youtu.be'], true);
        $channelId = null;
        $handle = null;

        if ($isYoutube && $host === 'youtu.be') {
            return ['is_youtube' => true, 'channel_id' => null, 'handle' => null];
        }

        if ($isYoutube && $first === 'channel' && $second) {
            $channelId = $second;
        } elseif ($isYoutube && str_starts_with($first, '@')) {
            $handle = $this->normalizeHandle($first);
        } elseif ($isYoutube && in_array($first, ['c', 'user'], true) && $second) {
            $handle = $this->normalizeHandle($second);
        }

        return ['is_youtube' => $isYoutube, 'channel_id' => $channelId, 'handle' => $handle];
    }

    private function existingPendingReport(?string $channelId, ?string $handle, string $channelUrl): ?YoutubeChannelReport
    {
        return YoutubeChannelReport::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($channelId, $handle, $channelUrl): void {
                if ($channelId) {
                    $query->orWhere('channel_id', $channelId);
                }

                if ($handle) {
                    $query->orWhere('handle', $handle);
                }

                $query->orWhere('channel_url', $channelUrl);
            })
            ->first();
    }

    private function findChannel(?string $channelId, ?string $handle, ?string $channelUrl): ?YoutubeChannel
    {
        return YoutubeChannel::query()
            ->where(function ($query) use ($channelId, $handle, $channelUrl): void {
                if ($channelId) {
                    $query->orWhere('channel_id', $channelId);
                }

                if ($handle) {
                    $query->orWhere('handle', $handle);
                }

                if ($channelUrl) {
                    $query->orWhere('channel_url', $channelUrl);
                }
            })
            ->first();
    }

    private function findLatestReport(?string $channelId, ?string $handle, ?string $channelUrl): ?YoutubeChannelReport
    {
        return YoutubeChannelReport::query()
            ->where(function ($query) use ($channelId, $handle, $channelUrl): void {
                if ($channelId) {
                    $query->orWhere('channel_id', $channelId);
                }

                if ($handle) {
                    $query->orWhere('handle', $handle);
                }

                if ($channelUrl) {
                    $query->orWhere('channel_url', $channelUrl);
                }
            })
            ->latest()
            ->first();
    }

    private function normalizeHandle(?string $handle): ?string
    {
        $handle = trim((string) $handle);
        $handle = ltrim($handle, '@');

        return $handle !== '' ? strtolower($handle) : null;
    }

    private function subjectKey(?string $channelId, ?string $handle, string $url): string
    {
        return $channelId ? "channel:{$channelId}" : ($handle ? "handle:{$handle}" : "url:{$url}");
    }

    private function weightFor(?User $user): float
    {
        return max(0.1, (float) ($user?->trust_score ?? 0.25));
    }

    private function reportPayload(YoutubeChannelReport $report): array
    {
        return Arr::only($report->toArray(), [
            'id',
            'channel_id',
            'handle',
            'channel_url',
            'channel_title',
            'channel_type',
            'status',
            'report_count',
            'weighted_score',
            'last_reported_at',
        ]);
    }

    private function channelPayload(YoutubeChannel $channel): array
    {
        return Arr::only($channel->toArray(), [
            'id',
            'media_outlet_id',
            'channel_id',
            'handle',
            'title',
            'channel_url',
            'channel_type',
            'status',
            'is_active',
            'updated_at',
        ]);
    }
}
