<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use Illuminate\Support\Carbon;

class OnboardingService
{
    public const VERSION = 1;

    public const REQUIRED_STEPS = [
        'open_demo',
        'install_extension',
        'sync_auth',
        'see_article_banner',
        'open_vote_panel',
        'open_event_context',
    ];

    public const DISMISSIBLE_SURFACES = [
        'site_modal',
        'banner_coach',
        'vote_panel_coach',
        'event_graph_coach',
        'popup_onboarding',
    ];

    public const BADGE_SLUG = 'truthshield-onboarded';

    public function stateFor(User $user): array
    {
        return $this->normalizeState($user->onboarding_state ?? []);
    }

    public function summaryFor(User $user): array
    {
        $state = $this->stateFor($user);
        $completed = $state['completed_steps'];
        $remaining = array_values(array_diff(self::REQUIRED_STEPS, $completed));

        return [
            'version' => self::VERSION,
            'required_steps' => self::REQUIRED_STEPS,
            'completed_steps' => $completed,
            'dismissed_surfaces' => $state['dismissed_surfaces'],
            'remaining_steps' => $remaining,
            'completed' => count($remaining) === 0,
            'completed_at' => $state['completed_at'],
            'reward_claimed_at' => $state['reward_claimed_at'],
            'badge_slug' => self::BADGE_SLUG,
        ];
    }

    public function merge(User $user, array $completedSteps = [], array $dismissedSurfaces = []): array
    {
        $state = $this->stateFor($user);

        $state['completed_steps'] = $this->uniqueKnownValues(
            array_merge($state['completed_steps'], $completedSteps),
            self::REQUIRED_STEPS,
        );
        $state['dismissed_surfaces'] = $this->uniqueKnownValues(
            array_merge($state['dismissed_surfaces'], $dismissedSurfaces),
            self::DISMISSIBLE_SURFACES,
        );

        if ($this->isComplete($state) && ! $state['completed_at']) {
            $state['completed_at'] = Carbon::now()->toISOString();
        }

        if ($this->isComplete($state) && ! $state['reward_claimed_at']) {
            $this->grantCompletionBadge($user);
            $state['reward_claimed_at'] = Carbon::now()->toISOString();
        }

        $user->forceFill(['onboarding_state' => $state])->save();

        return $this->summaryFor($user->fresh());
    }

    public function definitions(): array
    {
        return [
            'version' => self::VERSION,
            'required_steps' => self::REQUIRED_STEPS,
            'dismissible_surfaces' => self::DISMISSIBLE_SURFACES,
            'badge_slug' => self::BADGE_SLUG,
        ];
    }

    private function normalizeState(array $state): array
    {
        return [
            'version' => (int) ($state['version'] ?? self::VERSION),
            'completed_steps' => $this->uniqueKnownValues($state['completed_steps'] ?? [], self::REQUIRED_STEPS),
            'dismissed_surfaces' => $this->uniqueKnownValues($state['dismissed_surfaces'] ?? [], self::DISMISSIBLE_SURFACES),
            'completed_at' => $state['completed_at'] ?? null,
            'reward_claimed_at' => $state['reward_claimed_at'] ?? null,
        ];
    }

    private function uniqueKnownValues(array $values, array $allowed): array
    {
        return collect($values)
            ->map(fn ($value) => is_string($value) ? trim($value) : '')
            ->filter(fn ($value) => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function isComplete(array $state): bool
    {
        return empty(array_diff(self::REQUIRED_STEPS, $state['completed_steps']));
    }

    private function grantCompletionBadge(User $user): void
    {
        $badge = Badge::query()->updateOrCreate(
            ['slug' => self::BADGE_SLUG],
            [
                'name' => '新手護盾完成',
                'description' => '完成 TruthShield 新手導覽任務。',
                'color' => '#facc15',
            ],
        );

        $user->badges()->syncWithoutDetaching([
            $badge->id => ['reason' => 'onboarding_completed'],
        ]);
    }
}
