<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountEdge;
use App\Models\AccountSignal;
use Illuminate\Http\JsonResponse;

class AccountGraphController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'signals_7d' => AccountSignal::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'edges' => AccountEdge::query()->count(),
            'high_risk_edges' => AccountEdge::query()->where('score', '>=', 50)->count(),
            'top_edges' => AccountEdge::query()->orderByDesc('score')->limit(20)->get(),
        ]);
    }
}
