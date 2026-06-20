<?php

namespace App\Services;

use App\Models\TrustScoreHistory;

class BotTrustRecoveryService
{
    public function __construct(private readonly TrustScoreService $trustScores)
    {
    }

    public function recoverEligible(): int
    {
        $recoveryDays = max(1, (int) config('truthshield_bot.trust_recovery_days', 30));
        $recovered = 0;

        TrustScoreHistory::query()
            ->with('user')
            ->where('reason', 'like', 'bot_challenge_failure:%')
            ->where('created_at', '<=', now()->subDays($recoveryDays))
            ->orderBy('id')
            ->chunkById(100, function ($histories) use (&$recovered): void {
                foreach ($histories as $history) {
                    if (! $history->user) {
                        continue;
                    }

                    $restoreReason = "bot_challenge_recovery:{$history->id}";
                    if (TrustScoreHistory::query()->where('reason', $restoreReason)->exists()) {
                        continue;
                    }

                    $hasRecentFailure = TrustScoreHistory::query()
                        ->where('user_id', $history->user_id)
                        ->where('reason', 'like', 'bot_challenge_failure:%')
                        ->where('created_at', '>', now()->subDays((int) config('truthshield_bot.trust_recovery_days', 30)))
                        ->exists();
                    if ($hasRecentFailure) {
                        continue;
                    }

                    $this->trustScores->adjust(
                        $history->user,
                        abs((float) $history->delta),
                        $restoreReason,
                        details: '連續 30 天沒有新的無感安全驗證失敗，系統已恢復先前扣除的信用分。',
                    );
                    $recovered++;
                }
            });

        return $recovered;
    }
}
