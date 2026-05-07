<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, AchievementService $achievements): JsonResponse
    {
        $user = $request->user();
        $achievementState = $achievements->sync($user);
        $user->load('badges');
        $achievementStats = $achievements->statsFor($user);

        return response()->json([
            'user' => $user,
            'title' => $achievements->titleFor($user, $achievementStats),
            'stats' => [
                'votes' => $achievementStats['votes'],
                'evidence_votes' => $achievementStats['evidence_votes'],
                'evidence_reactions' => $achievementStats['evidence_reactions'],
                'helpful_reactions' => $achievementStats['helpful_reactions'],
                'helpful_evidence_received' => $achievementStats['helpful_evidence_received'],
                'trust_history_entries' => $achievementStats['trust_history_entries'],
                'read_sessions' => $achievementStats['read_sessions'],
                'snapshot_reports' => $achievementStats['snapshot_reports'],
                'snapshots_guarded' => $achievementStats['snapshots_guarded'],
                'unread_notifications' => $user->notifications()->whereNull('read_at')->count(),
            ],
            'achievement_summary' => [
                'unlocked_now' => $achievementState['unlocked'],
                'unlocked_count' => $achievementStats['badges'],
                'total_count' => count($achievements->definitions()),
            ],
            'achievements' => $achievements->achievementsFor($user),
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
