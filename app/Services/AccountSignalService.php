<?php

namespace App\Services;

use App\Models\AccountSignal;
use App\Models\NewsUrl;
use App\Models\User;
use Illuminate\Http\Request;

class AccountSignalService
{
    public function record(Request $request, ?User $user, ?NewsUrl $newsUrl, string $source): void
    {
        if (! $user) {
            return;
        }

        $signals = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        foreach ($signals as $type => $value) {
            if (! $value) {
                continue;
            }

            AccountSignal::query()->create([
                'user_id' => $user->id,
                'news_url_id' => $newsUrl?->id,
                'signal_type' => $type,
                'signal_hash' => hash('sha256', (string) $value),
                'source' => $source,
                'metadata' => ['captured_at' => now()->toJSON()],
            ]);
        }
    }
}
