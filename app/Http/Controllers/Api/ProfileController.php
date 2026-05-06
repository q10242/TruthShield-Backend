<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'stats' => [
                'votes' => $user->votes()->count(),
                'evidence_reactions' => $user->evidenceReactions()->count(),
                'helpful_reactions' => $user->evidenceReactions()->where('helpful', true)->count(),
                'trust_history_entries' => $user->trustScoreHistories()->count(),
                'read_sessions' => $user->readSessions()->count(),
                'unread_notifications' => $user->notifications()->whereNull('read_at')->count(),
            ],
            'recent_votes' => $user->votes()
                ->with(['tag:id,name,slug,color,severity', 'newsUrl:id,normalized_url,title_snapshot,finalized_at,voting_closes_at'])
                ->latest()
                ->limit(12)
                ->get(),
            'notifications' => $user->notifications()
                ->latest()
                ->limit(12)
                ->get(),
            'badges' => $user->badges()->get(),
            'trust_history' => $user->trustScoreHistories()
                ->with('newsUrl:id,normalized_url,title_snapshot')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }
}
