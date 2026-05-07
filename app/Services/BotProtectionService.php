<?php

namespace App\Services;

use App\Models\AbuseEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BotProtectionService
{
    public function __construct(
        private readonly TurnstileService $turnstile,
        private readonly ExtensionNonceService $extensionNonces,
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
            if (! $this->turnstile->verify($token, $request)) {
                $this->recordBotEvent($request, $action, 'challenge_required', 'medium', $risk);

                return response()->json([
                    'message' => 'Bot challenge required.',
                    'bot_protection' => [
                        'challenge_required' => true,
                        'provider' => 'turnstile',
                        'site_key' => config('truthshield_bot.turnstile_site_key'),
                        'risk_score' => $risk,
                        'action' => $action,
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
            'challenge_threshold' => (int) config('truthshield_bot.challenge_threshold', 50),
            'protected_actions' => config('truthshield_bot.challenge_actions', []),
        ];
    }

    private function requiresChallenge(string $action, int $risk): bool
    {
        return (bool) config('truthshield_bot.turnstile_enabled', false)
            && in_array($action, config('truthshield_bot.challenge_actions', []), true)
            && $risk >= (int) config('truthshield_bot.challenge_threshold', 50);
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
}
