<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsDomain;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;

class ExtensionSummaryController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'active_domains' => NewsDomain::query()->where('is_active', true)->count(),
            'votes' => Vote::query()->count(),
            'api_origin' => config('app.url'),
            'generated_at' => now()->toJSON(),
        ]);
    }
}
