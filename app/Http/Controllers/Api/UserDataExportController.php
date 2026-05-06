<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDataExportController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('badges');

        return response()->json([
            'exported_at' => now()->toJSON(),
            'user' => $user,
            'votes' => $user->votes()
                ->with(['tag:id,name,slug', 'newsUrl:id,normalized_url,title_snapshot,finalized_at'])
                ->latest()
                ->get(),
            'evidence_reactions' => $user->evidenceReactions()
                ->with('vote:id,evidence_url,evidence_note')
                ->latest()
                ->get(),
            'trust_history' => $user->trustScoreHistories()
                ->with('newsUrl:id,normalized_url,title_snapshot')
                ->latest()
                ->get(),
            'notifications' => $user->notifications()->latest()->get(),
            'read_sessions' => $user->readSessions()
                ->with('newsUrl:id,normalized_url,title_snapshot')
                ->latest()
                ->get(),
        ]);
    }
}
