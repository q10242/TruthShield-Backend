<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TrustLeaderboardController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('badges:id,name,slug,color')
            ->withCount('votes')
            ->orderByDesc('trust_score')
            ->limit(50)
            ->get(['id', 'name', 'display_name', 'is_real_name_public', 'public_identity_label', 'trust_score'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->publicName(),
                'public_identity_label' => $user->public_identity_label,
                'trust_score' => $user->trust_score,
                'votes_count' => $user->votes_count,
                'badges' => $user->badges
                    ->map(fn ($badge): array => [
                        'id' => $badge->id,
                        'name' => $badge->name,
                        'slug' => $badge->slug,
                        'color' => $badge->color,
                    ])
                    ->values(),
            ]);

        return response()->json([
            'data' => $users,
        ]);
    }
}
