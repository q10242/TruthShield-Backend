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

    public function selectorChecks(): JsonResponse
    {
        return response()->json([
            'data' => ExtensionSelectorCheck::query()
                ->latest('checked_at')
                ->limit(100)
                ->get(['id', 'domain', 'check_type', 'success', 'selector', 'metadata', 'checked_at']),
            'summary' => [
                'total' => ExtensionSelectorCheck::query()->count(),
                'failed_24h' => ExtensionSelectorCheck::query()->actionableFailures()->where('checked_at', '>=', now()->subDay())->count(),
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
