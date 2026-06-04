<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AchievementService;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, AchievementService $achievements, OnboardingService $onboarding): JsonResponse
    {
        $user = $request->user();
        $achievementState = $achievements->sync($user);
        $user->load('badges');
        $achievementStats = $achievements->statsFor($user);

        return response()->json([
            'user' => $user,
            'email_preferences' => array_replace(
                config('truthshield.email_preferences', []),
                $user->email_preferences ?? [],
            ),
            'title' => $achievements->titleFor($user, $achievementStats),
            'stats' => [
                'votes' => $achievementStats['votes'],
                'evidence_votes' => $achievementStats['evidence_votes'],
                'evidence_reactions' => $achievementStats['evidence_reactions'],
                'helpful_reactions' => $achievementStats['helpful_reactions'],
                'helpful_evidence_received' => $achievementStats['helpful_evidence_received'],
                'community_signals' => $achievementStats['community_signals'],
                'accepted_community_signals' => $achievementStats['accepted_community_signals'],
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
            'community_roles' => $achievements->communityRolesFor($user, $achievementStats),
            'onboarding_summary' => $onboarding->summaryFor($user),
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
            'verified_claimants' => $user->verifiedClaimants()
                ->with('newsUrl:id,normalized_url,title_snapshot')
                ->latest()
                ->limit(20)
                ->get(),
            'official_responses' => $user->officialResponses()
                ->with('newsUrl:id,normalized_url,title_snapshot')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:80'],
            'is_real_name_public' => ['required', 'boolean'],
            'profile_bio' => ['nullable', 'string', 'max:500'],
            'email_preferences' => ['nullable', 'array'],
            'email_preferences.account' => ['boolean'],
            'email_preferences.moderation' => ['boolean'],
            'email_preferences.official_response' => ['boolean'],
            'email_preferences.donation' => ['boolean'],
            'email_preferences.bug_report' => ['boolean'],
            'email_preferences.product' => ['boolean'],
        ]);

        $emailPreferences = array_intersect_key(
            $validated['email_preferences'] ?? [],
            config('truthshield.email_preferences', []),
        );

        $request->user()->forceFill([
            'display_name' => $validated['display_name'],
            'is_real_name_public' => $validated['is_real_name_public'],
            'profile_bio' => $validated['profile_bio'] ?? null,
            'email_preferences' => array_replace(
                config('truthshield.email_preferences', []),
                $emailPreferences,
            ),
        ])->save();

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $request->user()->fresh(),
        ]);
    }
}
