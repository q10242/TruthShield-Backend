<?php

namespace App\Services;

use App\Models\NewsUrl;
use App\Models\TrustScoreHistory;
use App\Models\TrustSettlement;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;

class TrustScoreService
{
    public function voteWeightFor(User $user): float
    {
        $score = max(0.0, (float) $user->trust_score);
        $identityMultiplier = $this->identityMultiplierFor($user);
        $abuseMultiplier = $this->abuseMultiplierFor($user);

        if ($score < $this->evidenceReactionMinTrustScore()) {
            $score = min($score, (float) config('truthshield.low_trust_vote_cap', 0.25));
        }

        return round($score * $identityMultiplier * $abuseMultiplier, 4);
    }

    public function identityMultiplierFor(User $user): float
    {
        $configured = config('truthshield.identity_multipliers.' . $user->identity_level);

        return (float) ($user->identity_multiplier ?: $configured ?: 1.0);
    }

    public function abuseMultiplierFor(User $user): float
    {
        $configured = config('truthshield.risk_multipliers.' . $user->risk_status);

        return (float) ($user->abuse_multiplier ?? $configured ?? 1.0);
    }

    public function canReactToEvidence(User $user): bool
    {
        return $this->voteWeightFor($user) >= $this->evidenceReactionMinTrustScore();
    }

    public function evidenceReactionMinTrustScore(): float
    {
        return (float) config('truthshield.evidence_reaction_min_trust_score', 0.5);
    }

    public function recalculate(User $user): float
    {
        // Reserved for future game-theory scoring and anti-abuse adjustments.
        return (float) $user->trust_score;
    }

    public function settleNewsUrl(NewsUrl $newsUrl): int
    {
        $status = $newsUrl->final_status_payload;
        $topTagId = data_get($status, 'top_tag.id');

        if (! $topTagId) {
            return 0;
        }

        $settled = 0;

        $newsUrl->votes()
            ->with(['user', 'reactions'])
            ->chunkById(100, function ($votes) use ($newsUrl, $topTagId, &$settled): void {
                foreach ($votes as $vote) {
                    $delta = $this->deltaForVote($vote, (int) $topTagId);

                    if (abs($delta) < 0.0001) {
                        continue;
                    }

                    DB::transaction(function () use ($vote, $newsUrl, $delta, &$settled): void {
                        $user = $vote->user()->lockForUpdate()->first();
                        if (! $user) {
                            return;
                        }

                        $algorithmVersion = $newsUrl->algorithm_version ?: config('truthshield.algorithm_version', 'truthshield-v1');
                        $alreadySettled = TrustSettlement::query()
                            ->where('news_url_id', $newsUrl->id)
                            ->where('user_id', $user->id)
                            ->where('vote_id', $vote->id)
                            ->where('algorithm_version', $algorithmVersion)
                            ->exists();

                        if ($alreadySettled) {
                            return;
                        }

                        $previous = (float) $user->trust_score;
                        $new = max(0.1, min(5.0, round($previous + $delta, 4)));
                        $user->forceFill(['trust_score' => $new])->save();

                        TrustScoreHistory::query()->create([
                            'user_id' => $user->id,
                            'news_url_id' => $newsUrl->id,
                            'previous_score' => $previous,
                            'delta' => round($new - $previous, 4),
                            'new_score' => $new,
                            'reason' => 'settlement',
                            'details' => 'Vote compared with finalized weighted consensus.',
                        ]);

                        TrustSettlement::query()->create([
                            'news_url_id' => $newsUrl->id,
                            'user_id' => $user->id,
                            'vote_id' => $vote->id,
                            'algorithm_version' => $algorithmVersion,
                            'delta' => round($new - $previous, 4),
                            'metadata' => [
                                'previous_score' => $previous,
                                'new_score' => $new,
                                'top_tag_id' => data_get($newsUrl->final_status_payload, 'top_tag.id'),
                            ],
                        ]);

                        UserNotification::query()->create([
                            'user_id' => $user->id,
                            'type' => 'trust.settled',
                            'title' => $delta >= 0 ? '信用分增加' : '信用分下降',
                            'body' => '新聞定案後，你的投票與社群加權共識完成結算。',
                            'action_url' => $newsUrl->normalized_url,
                            'metadata' => [
                                'news_url_id' => $newsUrl->id,
                                'delta' => round($new - $previous, 4),
                                'new_score' => $new,
                            ],
                        ]);

                        $settled++;
                    });
                }
            });

        return $settled;
    }

    public function adjust(User $user, float $delta, string $reason, ?NewsUrl $newsUrl = null, ?string $details = null): User
    {
        $previous = (float) $user->trust_score;
        $new = max(0.1, min(5.0, round($previous + $delta, 4)));

        $user->forceFill(['trust_score' => $new])->save();

        TrustScoreHistory::query()->create([
            'user_id' => $user->id,
            'news_url_id' => $newsUrl?->id,
            'previous_score' => $previous,
            'delta' => round($new - $previous, 4),
            'new_score' => $new,
            'reason' => $reason,
            'details' => $details,
        ]);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'trust.adjusted',
            'title' => $delta >= 0 ? '信用分已調升' : '信用分已調降',
            'body' => $details ?: '管理員已調整你的信用分。',
            'action_url' => $newsUrl?->normalized_url,
            'metadata' => [
                'delta' => round($new - $previous, 4),
                'new_score' => $new,
                'reason' => $reason,
            ],
        ]);

        return $user;
    }

    private function deltaForVote(Vote $vote, int $topTagId): float
    {
        $helpful = (float) $vote->reactions->where('helpful', true)->sum('weight_score');
        $unhelpful = (float) $vote->reactions->where('helpful', false)->sum('weight_score');
        $trustedBonus = $vote->evidence_safety === 'trusted' ? 0.02 : 0.0;
        $minorityBonus = $vote->weight_score > 0 && (int) $vote->tag_id !== $topTagId && $helpful > $unhelpful ? 0.04 : 0.0;
        $evidenceBonus = max(-0.06, min(0.1, ($helpful - $unhelpful) * 0.01 + $trustedBonus + $minorityBonus));
        $consensusDelta = (int) $vote->tag_id === $topTagId ? 0.05 : -0.03;

        return $consensusDelta + $evidenceBonus;
    }
}
