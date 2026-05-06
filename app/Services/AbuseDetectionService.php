<?php

namespace App\Services;

use App\Models\AbuseEvent;
use App\Models\NewsUrl;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Http\Request;

class AbuseDetectionService
{
    public function inspectVote(Request $request, User $user, NewsUrl $newsUrl, Vote $vote): void
    {
        $recentUserVotes = Vote::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentUserVotes >= 8) {
            $this->record($user, $newsUrl, 'rapid_user_votes', 'medium', ['recent_votes' => $recentUserVotes]);
            $this->restrict($user, 'watched', 0.5);
        }

        if ($vote->evidence_url) {
            $duplicateEvidence = Vote::query()
                ->where('evidence_url', $vote->evidence_url)
                ->where('id', '!=', $vote->id)
                ->count();

            if ($duplicateEvidence >= 3) {
                $this->record($user, $newsUrl, 'duplicate_evidence_url', 'medium', [
                    'evidence_url' => $vote->evidence_url,
                    'duplicates' => $duplicateEvidence,
                ]);
                $this->restrict($user, 'watched', 0.5);
            }
        }

        $sameTagBurst = Vote::query()
            ->where('news_url_id', $newsUrl->id)
            ->where('tag_id', $vote->tag_id)
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->distinct('user_id')
            ->count('user_id');

        if ($sameTagBurst >= 10) {
            $this->record($user, $newsUrl, 'coordinated_tag_burst', 'high', [
                'tag_id' => $vote->tag_id,
                'recent_users' => $sameTagBurst,
            ]);
            $this->restrict($user, 'limited', 0.1);
        }

        $secondsRead = (int) $user->readSessions()
            ->where('news_url_id', $newsUrl->id)
            ->value('seconds_read');

        if ($secondsRead < (int) config('truthshield.min_read_seconds_before_vote', 15)) {
            $this->record($user, $newsUrl, 'low_read_time_vote_attempt', 'medium', ['seconds_read' => $secondsRead]);
        }

        if ($user->created_at && $user->created_at->gt(now()->subHour()) && $recentUserVotes >= 3) {
            $this->record($user, $newsUrl, 'new_account_vote_burst', 'medium', ['recent_votes' => $recentUserVotes]);
            $this->restrict($user, 'watched', 0.5);
        }
    }

    public function inspectReaction(Request $request, User $user, Vote $vote): void
    {
        $recentReactions = $user->evidenceReactions()
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentReactions >= 12) {
            $this->record($user, $vote->newsUrl, 'rapid_evidence_reactions', 'medium', [
                'recent_reactions' => $recentReactions,
            ]);
            $this->restrict($user, 'watched', 0.5);
        }
    }

    private function record(?User $user, ?NewsUrl $newsUrl, string $type, string $severity, array $metadata = []): void
    {
        AbuseEvent::query()->create([
            'user_id' => $user?->id,
            'news_url_id' => $newsUrl?->id,
            'type' => $type,
            'severity' => $severity,
            'metadata' => $metadata,
        ]);
    }

    private function restrict(User $user, string $riskStatus, float $abuseMultiplier): void
    {
        $current = (float) ($user->abuse_multiplier ?? 1.0);

        if ($current <= $abuseMultiplier && $user->risk_status !== 'normal') {
            return;
        }

        $user->forceFill([
            'risk_status' => $riskStatus,
            'abuse_multiplier' => min($current, $abuseMultiplier),
        ])->save();
    }
}
