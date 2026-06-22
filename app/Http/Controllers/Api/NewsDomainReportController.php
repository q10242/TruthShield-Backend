<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsDomain;
use App\Models\NewsDomainReport;
use App\Services\BotProtectionService;
use App\Services\CommunitySignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsDomainReportController extends Controller
{
    public function store(Request $request, CommunitySignalService $signals, BotProtectionService $botProtection): JsonResponse
    {
        if ($response = $botProtection->enforce($request, 'domain.report')) {
            return $response;
        }

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:4096'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'challenge_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $domain = strtolower((string) parse_url($validated['url'], PHP_URL_HOST));

        if (! $domain) {
            return response()->json([
                'message' => 'Unable to parse domain from URL.',
                'errors' => [
                    'url' => ['Unable to parse domain from URL.'],
                ],
            ], 422);
        }

        if (NewsDomain::query()->where('domain', $domain)->exists()) {
            return response()->json([
                'error_code' => 'domain_already_covered',
                'message' => '這個網站已在 TruthShield 的支援清單中，無需回報。',
            ], 409);
        }

        $report = NewsDomainReport::query()->where('domain', $domain)->where('status', 'pending')->first();

        if ($report) {
            $report->forceFill([
                'url' => $validated['url'],
                'page_title' => $validated['page_title'] ?? $report->page_title,
                'note' => $validated['note'] ?? $report->note,
                'report_count' => $report->report_count + 1,
                'last_reported_at' => now(),
            ])->save();
        } else {
            $report = NewsDomainReport::query()->create([
                'domain' => $domain,
                'url' => $validated['url'],
                'page_title' => $validated['page_title'] ?? null,
                'note' => $validated['note'] ?? null,
                'status' => 'pending',
                'report_count' => 1,
                'last_reported_at' => now(),
            ]);
        }

        $signals->record(
            $request,
            'domain_report',
            $report,
            $domain,
            'missing_news_domain',
            ['url' => $validated['url'], 'page_title' => $validated['page_title'] ?? null],
        );

        return response()->json([
            'message' => 'News domain report received.',
            'report' => $report,
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:4096'],
        ]);

        $domain = $validated['domain'] ?? null;

        if (! $domain && ! empty($validated['url'])) {
            $domain = parse_url($validated['url'], PHP_URL_HOST);
        }

        $domain = strtolower((string) $domain);

        if (! $domain) {
            return response()->json(['message' => 'domain or url is required.'], 422);
        }

        $report = NewsDomainReport::query()
            ->where('domain', $domain)
            ->latest('updated_at')
            ->first();

        return response()->json([
            'domain' => $domain,
            'report' => $report,
            'is_reported' => (bool) $report,
        ]);
    }
}
