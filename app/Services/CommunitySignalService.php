<?php

namespace App\Services;

use App\Models\CommunitySignal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CommunitySignalService
{
    public function __construct(private readonly CommunityPolicyService $policy)
    {
    }

    public function record(Request $request, string $signalType, Model $subject, string $subjectKey, ?string $value = null, array $metadata = []): CommunitySignal
    {
        $user = $this->optionalUser($request);
        $weight = $this->weightFor($user);
        $attributes = [
            'signal_type' => $signalType,
            'subject_key' => $subjectKey,
            'user_id' => $user?->id,
        ];
        $payload = [
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'value' => $value,
            'weight_score' => $weight,
            'metadata' => [
                ...$metadata,
                'authenticated' => (bool) $user,
                'recorded_from' => $request->path(),
            ],
        ];

        if ($user) {
            return CommunitySignal::query()->updateOrCreate($attributes, $payload);
        }

        return CommunitySignal::query()->create([...$attributes, ...$payload]);
    }

    public function optionalUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        $tokenable = $accessToken?->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }

    private function weightFor(?User $user): float
    {
        if (! $user) {
            return 0.05;
        }

        if ((float) $user->trust_score < $this->policy->trustFloor()) {
            return max(0.1, (float) $user->trust_score * 0.25);
        }

        return max(0.1, (float) $user->trust_score);
    }
}
