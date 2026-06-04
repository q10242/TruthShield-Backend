<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function show(Request $request, OnboardingService $onboarding): JsonResponse
    {
        return response()->json([
            'definitions' => $onboarding->definitions(),
            'summary' => $onboarding->summaryFor($request->user()),
        ]);
    }

    public function update(Request $request, OnboardingService $onboarding): JsonResponse
    {
        $validated = $request->validate([
            'completed_steps' => ['sometimes', 'array'],
            'completed_steps.*' => ['string', Rule::in(OnboardingService::REQUIRED_STEPS)],
            'dismissed_surfaces' => ['sometimes', 'array'],
            'dismissed_surfaces.*' => ['string', Rule::in(OnboardingService::DISMISSIBLE_SURFACES)],
        ]);

        return response()->json([
            'definitions' => $onboarding->definitions(),
            'summary' => $onboarding->merge(
                $request->user(),
                $validated['completed_steps'] ?? [],
                $validated['dismissed_surfaces'] ?? [],
            ),
        ]);
    }
}
