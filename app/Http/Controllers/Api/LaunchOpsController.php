<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtensionSelectorCheck;
use App\Models\NewsDomain;
use App\Models\RateLimitPolicy;
use App\Models\TrustedEvidenceSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaunchOpsController extends Controller
{
    public function trustedEvidenceSources(): JsonResponse
    {
        return response()->json([
            'data' => TrustedEvidenceSource::query()
                ->where('is_active', true)
                ->orderBy('host')
                ->get(['host', 'source_type', 'trust_bonus', 'notes']),
        ]);
    }

    public function rateLimitPolicies(): JsonResponse
    {
        return response()->json([
            'data' => RateLimitPolicy::query()
                ->where('is_active', true)
                ->orderBy('scope')
                ->get(['name', 'scope', 'max_attempts', 'decay_seconds', 'low_trust_multiplier']),
        ]);
    }

    public function selectorChecks(Request $request): JsonResponse
    {
        $limit = max(1, min(500, (int) $request->integer('limit', 100)));
        $hours = $request->integer('hours');
        $hours = $hours ? max(1, min(720, (int) $hours)) : null;

        $checks = ExtensionSelectorCheck::query()
            ->latest('checked_at');

        if ($request->boolean('failed')) {
            $checks->actionableFailures();
        } elseif ($request->has('success')) {
            $checks->where('success', $request->boolean('success'));
        }

        if ($hours) {
            $checks->where('checked_at', '>=', now()->subHours($hours));
        }

        return response()->json([
            'data' => $checks
                ->limit($limit)
                ->get(['id', 'domain', 'check_type', 'success', 'selector', 'metadata', 'checked_at']),
            'summary' => [
                'total' => ExtensionSelectorCheck::query()->count(),
                'failed_24h' => ExtensionSelectorCheck::query()->actionableFailures()->where('checked_at', '>=', now()->subDay())->count(),
                'query' => [
                    'failed' => $request->boolean('failed'),
                    'success' => $request->has('success') ? $request->boolean('success') : null,
                    'hours' => $hours,
                    'limit' => $limit,
                ],
            ],
        ]);
    }

    public function storeSelectorCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'check_type' => ['required', 'string', 'max:80'],
            'success' => ['required', 'boolean'],
            'selector' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ]);

        $domain = NewsDomain::query()->where('domain', strtolower($validated['domain']))->first();
        $check = ExtensionSelectorCheck::query()->create([
            ...$validated,
            'domain' => strtolower($validated['domain']),
            'news_domain_id' => $domain?->id,
            'checked_at' => now(),
        ]);

        return response()->json(['check' => $check], 201);
    }
}
