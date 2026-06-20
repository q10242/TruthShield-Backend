<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TurnstileService
{
    /**
     * @return array{status: string, error_codes: array<int, string>}
     */
    public function verify(?string $token, Request $request, string $action): array
    {
        if (! (bool) config('truthshield_bot.turnstile_enabled', false)) {
            return ['status' => 'success', 'error_codes' => []];
        }

        if (in_array(app()->environment(), ['local', 'testing'], true) && $token === 'local-pass') {
            return ['status' => 'success', 'error_codes' => []];
        }

        $secret = config('truthshield_bot.turnstile_secret');
        if (! $secret || ! $token) {
            return ['status' => 'missing', 'error_codes' => []];
        }

        try {
            $response = Http::asForm()
                ->timeout(3)
                ->post(config('truthshield_bot.turnstile_verify_url'), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                    'idempotency_key' => (string) $request->header('X-TruthShield-Challenge-Request', ''),
                ]);
        } catch (\Throwable) {
            return ['status' => 'provider_unavailable', 'error_codes' => []];
        }

        if (! $response->successful()) {
            return ['status' => 'provider_unavailable', 'error_codes' => []];
        }

        $errors = collect($response->json('error-codes', []))->filter()->map(fn ($value) => (string) $value)->values()->all();
        if (! (bool) $response->json('success', false)) {
            $status = in_array('timeout-or-duplicate', $errors, true) ? 'replay' : 'invalid';

            return ['status' => $status, 'error_codes' => $errors];
        }

        $expectedHostname = trim((string) config('truthshield_bot.expected_hostname'));
        $hostname = trim((string) $response->json('hostname', ''));
        if ($expectedHostname !== '' && $hostname !== '' && ! hash_equals($expectedHostname, $hostname)) {
            return ['status' => 'invalid', 'error_codes' => ['hostname-mismatch']];
        }

        $challengeTimestamp = trim((string) $response->json('challenge_ts', ''));
        if ($challengeTimestamp !== '') {
            try {
                $issuedAt = CarbonImmutable::parse($challengeTimestamp);
                if ($issuedAt->lt(now()->subMinutes(5)) || $issuedAt->gt(now()->addMinute())) {
                    return ['status' => 'invalid', 'error_codes' => ['token-expired']];
                }
            } catch (\Throwable) {
                return ['status' => 'invalid', 'error_codes' => ['invalid-challenge-timestamp']];
            }
        }

        $verifiedAction = trim((string) $response->json('action', ''));
        $expectedAction = substr((string) preg_replace('/[^a-zA-Z0-9_-]/', '_', $action), 0, 32);
        if ($verifiedAction !== '' && ! hash_equals($expectedAction, $verifiedAction)) {
            return ['status' => 'invalid', 'error_codes' => ['action-mismatch']];
        }

        return ['status' => 'success', 'error_codes' => []];
    }
}
