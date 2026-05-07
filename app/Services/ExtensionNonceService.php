<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ExtensionNonceService
{
    public function issue(): array
    {
        $nonce = Str::random(32);
        $expiresAt = now()->addSeconds((int) config('truthshield_bot.extension_nonce_ttl_seconds', 300));
        $payload = "{$nonce}|{$expiresAt->timestamp}";
        $signature = hash_hmac('sha256', $payload, $this->secret());

        Cache::store(config('truthshield.status_cache_store'))->put($this->cacheKey($nonce), $expiresAt->timestamp, $expiresAt);

        return [
            'nonce' => $nonce,
            'expires_at' => $expiresAt->toJSON(),
            'signature' => $signature,
        ];
    }

    public function validRequest(Request $request): bool
    {
        $nonce = (string) $request->header('X-TruthShield-Extension-Nonce', '');
        $signature = (string) $request->header('X-TruthShield-Extension-Signature', '');
        $expiresAt = Cache::store(config('truthshield.status_cache_store'))->get($this->cacheKey($nonce));

        if (! $nonce || ! $signature || ! $expiresAt || $expiresAt < now()->timestamp) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$nonce}|{$expiresAt}", $this->secret());

        return hash_equals($expected, $signature);
    }

    private function cacheKey(string $nonce): string
    {
        return "extension:nonce:{$nonce}";
    }

    private function secret(): string
    {
        return (string) config('app.key');
    }
}
