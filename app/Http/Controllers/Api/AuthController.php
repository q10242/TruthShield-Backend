<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OauthLoginState;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function oauthBegin(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['facebook', 'google', 'github'], true), 404);

        $validated = $request->validate([
            'redirect_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $plainState = Str::random(48);
        $state = OauthLoginState::query()->create([
            'provider' => $provider,
            'state_hash' => hash('sha256', $plainState),
            'redirect_url' => $validated['redirect_url'] ?? null,
            'user_id' => $request->user()?->id,
            'expires_at' => now()->addMinutes(10),
            'metadata' => ['source' => 'oauth_begin'],
        ]);

        return response()->json([
            'provider' => $provider,
            'state' => $plainState,
            'expires_at' => $state->expires_at->toJSON(),
            'auth_url' => url("/api/auth/{$provider}/redirect?state={$plainState}"),
        ]);
    }

    public function socialiteRedirect(Request $request, string $provider)
    {
        abort_unless(in_array($provider, ['facebook', 'google', 'github'], true), 404);

        return Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $request->query('state')])
            ->redirect();
    }

    public function socialiteCallback(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['facebook', 'google', 'github'], true), 404);

        $socialiteUser = Socialite::driver($provider)->stateless()->user();
        $email = $socialiteUser->getEmail();

        if (! $email) {
            return response()->json(['message' => 'OAuth provider did not return a verified email.'], 422);
        }

        $synthetic = Request::create('/internal/oauth-callback', 'POST', [
            'provider_user_id' => (string) $socialiteUser->getId(),
            'email' => $email,
            'name' => $socialiteUser->getName() ?: $socialiteUser->getNickname(),
            'state' => $request->query('state'),
        ]);

        return $this->oauthCallback($synthetic, $provider);
    }

    public function devLogin(Request $request): JsonResponse
    {
        if (! config('truthshield.dev_login_enabled', false)) {
            return response()->json(['message' => 'Dev login is disabled.'], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'fb_id' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'] ?? Str::before($validated['email'], '@'),
                'fb_id' => $validated['fb_id'] ?? null,
                'auth_provider' => filled($validated['fb_id'] ?? null) ? 'facebook-dev' : 'dev',
                'identity_level' => 'dev',
                'identity_multiplier' => (float) config('truthshield.identity_multipliers.dev', 0.8),
                'password' => Str::password(32),
            ],
        );

        $token = $user->createToken('truthshield-iframe', ['vote'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'fb_id' => $user->fb_id,
                'auth_provider' => $user->auth_provider,
                'identity_level' => $user->identity_level,
                'risk_status' => $user->risk_status,
                'trust_score' => $user->trust_score,
            ],
        ]);
    }

    public function oauthCallback(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['facebook', 'google', 'github'], true), 404);

        $validated = $request->validate([
            'provider_user_id' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:120'],
        ]);

        if (! empty($validated['state'])) {
            $state = OauthLoginState::query()
                ->where('provider', $provider)
                ->where('state_hash', hash('sha256', $validated['state']))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->first();

            if (! $state) {
                return response()->json(['message' => 'OAuth state is invalid or expired.'], 422);
            }

            $state->forceFill(['used_at' => now()])->save();
        }

        $identity = UserIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $validated['provider_user_id'])
            ->first();

        $identityLevel = $provider === 'facebook' ? 'verified_social' : 'oauth';
        $identityMultiplier = (float) config("truthshield.identity_multipliers.{$identityLevel}", 1.0);

        $user = $identity?->user ?: User::query()->firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'] ?? Str::before($validated['email'], '@'),
                'auth_provider' => $provider,
                'fb_id' => $provider === 'facebook' ? $validated['provider_user_id'] : null,
                'identity_level' => $identityLevel,
                'identity_multiplier' => $identityMultiplier,
                'password' => Str::password(32),
            ],
        );

        $user->forceFill([
            'name' => $validated['name'] ?? $user->name,
            'auth_provider' => $provider,
            'fb_id' => $provider === 'facebook' ? $validated['provider_user_id'] : $user->fb_id,
            'identity_level' => $identityLevel,
            'identity_multiplier' => max((float) $user->identity_multiplier, $identityMultiplier),
        ])->save();

        UserIdentity::query()->updateOrCreate(
            ['provider' => $provider, 'provider_user_id' => $validated['provider_user_id']],
            [
                'user_id' => $user->id,
                'email' => $validated['email'],
                'display_name' => $validated['name'] ?? $user->name,
                'verified_at' => now(),
                'metadata' => ['source' => 'api_callback_stub'],
            ],
        );

        $token = $user->createToken("truthshield-{$provider}", ['vote'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->fresh()->load('identities'),
        ]);
    }

    public function linkIdentity(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['facebook', 'google', 'github'], true), 404);

        $validated = $request->validate([
            'provider_user_id' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $identity = UserIdentity::query()->updateOrCreate(
            ['provider' => $provider, 'provider_user_id' => $validated['provider_user_id']],
            [
                'user_id' => $request->user()->id,
                'email' => $validated['email'] ?? $request->user()->email,
                'display_name' => $validated['display_name'] ?? $request->user()->name,
                'verified_at' => now(),
                'metadata' => ['source' => 'identity_link'],
            ],
        );

        return response()->json(['identity' => $identity]);
    }
}
