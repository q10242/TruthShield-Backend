<?php

namespace App\Services;

use App\Models\AbuseEvent;
use App\Models\TrustScoreHistory;
use App\Services\TrustScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class BotProtectionService
{
    public function __construct(
        private readonly TurnstileService $turnstile,
        private readonly ExtensionNonceService $extensionNonces,
        private readonly TrustScoreService $trustScores,
    ) {
    }

    public function enforce(Request $request, string $action): ?JsonResponse
    {
        if (! (bool) config('truthshield_bot.enabled', false)) {
            return null;
        }

        $risk = $this->riskScore($request, $action);

        if ($risk >= (int) config('truthshield_bot.block_threshold', 90)) {
            $this->recordBotEvent($request, $action, 'bot_blocked', 'high', $risk);

            return response()->json([
                'message' => 'Request blocked by bot protection.',
                'bot_protection' => ['risk_score' => $risk, 'action' => $action],
            ], 403);
        }

        if ($this->requiresChallenge($action, $risk)) {
            $token = $request->input('challenge_token') ?: $request->header('X-TruthShield-Challenge-Token');
            $result = $this->turnstile->verify($token, $request, $action);

            if ($result['status'] === 'provider_unavailable') {
                if ($this->fallbackRateLimited($request, $action)) {
                    return response()->json([
                        'message' => 'Security verification is temporarily unavailable. Please try again shortly.',
                        'bot_protection' => ['provider_unavailable' => true, 'action' => $action],
                    ], 429);
                }

                $this->recordBotEvent($request, $action, 'challenge_provider_unavailable', 'low', $risk);

                return null;
            }

            if ($result['status'] !== 'success') {
                $isRetry = $request->boolean('challenge_retry') || $request->header('X-TruthShield-Challenge-Retry') === '1';
                if ($isRetry && in_array($result['status'], ['invalid', 'replay'], true)) {
                    $this->recordChallengeFailure($request, $action, $result['status'], $risk);
                } else {
                    $this->recordBotEvent($request, $action, 'challenge_required', 'medium', $risk);
                }

                return response()->json([
                    'message' => 'Bot challenge required.',
                    'bot_protection' => [
                        'challenge_required' => true,
                        'provider' => 'turnstile',
                        'site_key' => config('truthshield_bot.turnstile_site_key'),
                        'risk_score' => $risk,
                        'action' => $action,
                        'verification_status' => $result['status'],
                        'retryable' => true,
                    ],
                ], 428);
            }
        }

        return null;
    }

    public function riskScore(Request $request, string $action): int
    {
        $score = 0;
        $user = $request->user();
        $userAgent = strtolower((string) $request->userAgent());

        foreach (config('truthshield_bot.blocked_user_agent_patterns', []) as $pattern) {
            if ($pattern && str_contains($userAgent, strtolower($pattern))) {
                $score += 50;
                break;
            }
        }

        if (! $user) {
            $score += 15;
        } else {
            if ((float) $user->trust_score < 0.5) {
                $score += 25;
            }
            if ($user->created_at && $user->created_at->gt(now()->subHour())) {
                $score += 15;
            }
            if ($user->risk_status !== 'normal') {
                $score += 35;
            }
        }

        if ($request->headers->has('X-TruthShield-Extension-Nonce')) {
            $score += $this->extensionNonces->validRequest($request) ? -15 : 20;
        }

        $attempts = $this->incrementAttempts($request, $action);
        if ($attempts > 5) {
            $score += min(40, ($attempts - 5) * 5);
        }

        return max(0, min(100, $score));
    }

    public function publicConfig(): array
    {
        return [
            'turnstile_enabled' => (bool) config('truthshield_bot.turnstile_enabled', false),
            'bot_protection_enabled' => (bool) config('truthshield_bot.enabled', false),
            'turnstile_site_key' => config('truthshield_bot.turnstile_site_key'),
            'challenge_mode' => config('truthshield_bot.challenge_mode', 'always'),
            'challenge_threshold' => (int) config('truthshield_bot.challenge_threshold', 50),
            'protected_actions' => config('truthshield_bot.challenge_actions', []),
            'fallback_limits' => [
                'per_minute' => (int) config('truthshield_bot.fallback_per_minute', 3),
                'per_hour' => (int) config('truthshield_bot.fallback_per_hour', 30),
            ],
        ];
    }

    private function requiresChallenge(string $action, int $risk): bool
    {
        if (! (bool) config('truthshield_bot.turnstile_enabled', false)
            || ! in_array($action, config('truthshield_bot.challenge_actions', []), true)) {
            return false;
        }

        return config('truthshield_bot.challenge_mode', 'always') === 'always'
            || $risk >= (int) config('truthshield_bot.challenge_threshold', 50);
    }

    private function incrementAttempts(Request $request, string $action): int
    {
        $subject = $request->user()?->id ? 'u:' . $request->user()->id : 'ip:' . $request->ip();
        $key = "bot:attempts:{$action}:{$subject}";
        $cache = Cache::store(config('truthshield.status_cache_store'));
        $count = (int) $cache->increment($key);

        if ($count === 1) {
            $cache->put($key, 1, now()->addMinute());
        }

        return $count;
    }

    private function recordBotEvent(Request $request, string $action, string $type, string $severity, int $risk): void
    {
        AbuseEvent::query()->create([
            'user_id' => $request->user()?->id,
            'type' => $type,
            'severity' => $severity,
            'metadata' => [
                'action' => $action,
                'risk_score' => $risk,
                'ip_hash' => hash('sha256', (string) $request->ip()),
                'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
                'extension_signature_valid' => $this->extensionNonces->validRequest($request),
            ],
        ]);
    }

    private function recordChallengeFailure(Request $request, string $action, string $status, int $risk): void
    {
        $this->recordBotEvent($request, $action, 'challenge_failed', 'medium', $risk);
        $user = $request->user();
        if (! $user) {
            return;
        }

        $cache = Cache::store(config('truthshield.status_cache_store'));
        $key = "bot:challenge-failures:u:{$user->id}";
        $count = (int) $cache->increment($key);
        if ($count === 1) {
            $cache->put($key, 1, now()->addMinutes((int) config('truthshield_bot.trust_failure_window_minutes', 10)));
        }

        $step = max(1, (int) config('truthshield_bot.trust_failure_step', 5));
        if ($count % $step !== 0) {
            return;
        }

        $delta = (float) config('truthshield_bot.trust_failure_delta', 0.05);
        $dailyMax = (float) config('truthshield_bot.trust_failure_daily_max', 0.20);
        $deductedToday = abs((float) TrustScoreHistory::query()
            ->where('user_id', $user->id)
            ->where('reason', 'like', 'bot_challenge_failure:%')
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('delta'));
        if ($deductedToday + 0.0001 >= $dailyMax) {
            return;
        }

        $applied = min($delta, max(0, $dailyMax - $deductedToday));
        $historyReason = 'bot_challenge_failure:'.now()->format('Ymd').':'.$count;
        $this->trustScores->adjust(
            $user,
            -$applied,
            $historyReason,
            details: "無感安全驗證在 10 分鐘內累積 {$count} 次明確失敗；可由通知中的申訴入口提出說明。",
        );

        $user->notifications()
            ->where('type', 'trust.adjusted')
            ->latest('id')
            ->limit(1)
            ->update(['action_url' => '/appeals?subject_type=bot_challenge&subject_id='.$user->id]);

        AbuseEvent::query()->create([
            'user_id' => $user->id,
            'type' => 'challenge_trust_deduction',
            'severity' => 'medium',
            'metadata' => [
                'action' => $action,
                'verification_status' => $status,
                'failure_count' => $count,
                'delta' => -$applied,
                'reason' => $historyReason,
            ],
        ]);
    }

    private function fallbackRateLimited(Request $request, string $action): bool
    {
        $subject = $request->user()?->id ? 'u:'.$request->user()->id : 'ip:'.$request->ip();
        $minuteKey = "bot:fallback:minute:{$action}:{$subject}";
        $hourKey = "bot:fallback:hour:{$action}:{$subject}";
        $minuteMax = max(1, (int) config('truthshield_bot.fallback_per_minute', 3));
        $hourMax = max(1, (int) config('truthshield_bot.fallback_per_hour', 30));

        if (RateLimiter::tooManyAttempts($minuteKey, $minuteMax)
            || RateLimiter::tooManyAttempts($hourKey, $hourMax)) {
            return true;
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);

        return false;
    }
}
