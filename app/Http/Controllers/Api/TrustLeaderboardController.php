<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TrustLeaderboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->with('badges:id,name,slug,color')
                ->withCount('votes')
                ->orderByDesc('trust_score')
                ->limit(50)
                ->get(['id', 'name', 'trust_score']),
        ]);
    }
}
