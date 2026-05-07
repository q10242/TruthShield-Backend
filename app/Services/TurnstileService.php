<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TurnstileService
{
    public function verify(?string $token, Request $request): bool
    {
        if (! (bool) config('truthshield_bot.turnstile_enabled', false)) {
            return true;
        }

        if (in_array(app()->environment(), ['local', 'testing'], true) && $token === 'local-pass') {
            return true;
        }

        $secret = config('truthshield_bot.turnstile_secret');
        if (! $secret || ! $token) {
            return false;
        }

        $response = Http::asForm()
            ->timeout(3)
            ->post(config('truthshield_bot.turnstile_verify_url'), [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

        return (bool) ($response->json('success') ?? false);
    }
}
