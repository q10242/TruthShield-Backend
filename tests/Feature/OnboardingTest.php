<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_requires_authentication(): void
    {
        $this->getJson('/api/me/onboarding')->assertUnauthorized();
        $this->patchJson('/api/me/onboarding', ['completed_steps' => ['open_demo']])->assertUnauthorized();
    }

    public function test_onboarding_rejects_unknown_steps_and_surfaces(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me/onboarding', [
                'completed_steps' => ['unknown_step'],
                'dismissed_surfaces' => ['unknown_surface'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['completed_steps.0', 'dismissed_surfaces.0']);
    }

    public function test_onboarding_progress_merges_without_removing_existing_steps(): void
    {
        $user = User::factory()->create([
            'onboarding_state' => [
                'version' => OnboardingService::VERSION,
                'completed_steps' => ['open_demo'],
                'dismissed_surfaces' => ['site_modal'],
            ],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me/onboarding', [
                'completed_steps' => ['install_extension', 'open_demo'],
                'dismissed_surfaces' => ['banner_coach'],
            ])
            ->assertOk();

        $this->assertSame(['open_demo', 'install_extension'], $response->json('summary.completed_steps'));
        $this->assertSame(['site_modal', 'banner_coach'], $response->json('summary.dismissed_surfaces'));

        $this->actingAs($user->fresh(), 'sanctum')
            ->patchJson('/api/me/onboarding', ['completed_steps' => []])
            ->assertJsonPath('summary.completed_steps', ['open_demo', 'install_extension']);
    }

    public function test_onboarding_completion_grants_badge_once_without_changing_trust_score(): void
    {
        $user = User::factory()->create(['trust_score' => 1.23]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me/onboarding', [
                'completed_steps' => OnboardingService::REQUIRED_STEPS,
            ])
            ->assertOk()
            ->assertJsonPath('summary.completed', true)
            ->assertJsonPath('summary.badge_slug', OnboardingService::BADGE_SLUG);

        $this->assertSame(1.23, (float) $user->fresh()->trust_score);
        $this->assertSame(1, Badge::query()->where('slug', OnboardingService::BADGE_SLUG)->count());
        $this->assertSame(1, $user->fresh()->badges()->where('slug', OnboardingService::BADGE_SLUG)->count());

        $firstRewardAt = $user->fresh()->onboarding_state['reward_claimed_at'];

        $this->actingAs($user->fresh(), 'sanctum')
            ->patchJson('/api/me/onboarding', [
                'completed_steps' => OnboardingService::REQUIRED_STEPS,
            ])
            ->assertOk();

        $this->assertSame(1, $user->fresh()->badges()->where('slug', OnboardingService::BADGE_SLUG)->count());
        $this->assertSame($firstRewardAt, $user->fresh()->onboarding_state['reward_claimed_at']);
        $this->assertSame(1.23, (float) $user->fresh()->trust_score);
    }

    public function test_profile_includes_onboarding_summary(): void
    {
        $user = User::factory()->create([
            'onboarding_state' => [
                'version' => OnboardingService::VERSION,
                'completed_steps' => ['open_demo'],
                'dismissed_surfaces' => [],
            ],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('onboarding_summary.completed_steps', ['open_demo'])
            ->assertJsonPath('onboarding_summary.remaining_steps.0', 'install_extension');
    }
}
