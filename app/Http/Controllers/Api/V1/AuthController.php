<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\AppleIdentityToken;
use App\Services\Auth\AppleTokenService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        return $this->tokenResponse($user, $request->input('device_name'));
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        return $this->tokenResponse($user, $request->input('device_name'), 201);
    }

    /**
     * Exchange the one-time code from the mobile Google OAuth redirect for an API token.
     */
    public function googleExchange(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = Cache::pull(GoogleController::mobileCodeCacheKey($request->code));
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => 'This Google sign-in has expired. Please try again.',
            ]);
        }

        return $this->tokenResponse($user, $request->input('device_name'));
    }

    /**
     * Sign in with Apple from the mobile app.
     *
     * The native sheet runs entirely on the device, so unlike Google there is no
     * browser round trip and no one-time code: the app posts the identity token
     * Apple signed, which is verified here against Apple's published keys.
     */
    public function apple(Request $request, AppleIdentityToken $tokens, AppleTokenService $apple): JsonResponse
    {
        $request->validate([
            'identity_token' => ['required', 'string'],
            'authorization_code' => ['nullable', 'string'],
            'raw_nonce' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $claims = $tokens->verify($request->string('identity_token')->toString(), $request->input('raw_nonce'));
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'identity_token' => 'Apple sign-in could not be verified. Please try again.',
            ]);
        }

        $user = User::where('apple_id', $claims['sub'])->first();

        if (! $user && $claims['email']) {
            // Same person signing in with Apple after registering by email or Google.
            $user = User::where('email', $claims['email'])->first();

            $user?->update(['apple_id' => $claims['sub']]);
        }

        if (! $user) {
            // Apple only releases the email on the first authorization. Without it
            // there is nothing to create an account from — the user has to let go
            // of the app under Settings → Apple ID → Sign in with Apple and retry.
            if (! $claims['email']) {
                throw ValidationException::withMessages([
                    'identity_token' => 'Apple did not share an email for this account. '
                        .'Open Settings → your name → Sign-In & Security → Sign in with Apple, '
                        .'remove Lectura, then try again.',
                ]);
            }

            $user = User::create([
                'name' => $request->filled('name')
                    ? $request->string('name')->toString()
                    : Str::before($claims['email'], '@'),
                'email' => $claims['email'],
                'apple_id' => $claims['sub'],
            ]);

            // email_verified_at is guarded, so it cannot ride along with the create.
            if ($claims['email_verified']) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            event(new Registered($user));
        }

        $this->rememberAppleRefreshToken($user, $request->input('authorization_code'), $claims['aud'], $apple);

        return $this->tokenResponse($user, $request->input('device_name'));
    }

    /**
     * Trades the sheet's authorization code for the refresh token that account
     * deletion later revokes with.
     *
     * The code is single-use and expires within minutes, so this has to happen
     * here — but a failure must never cost the user their sign-in, so it is only
     * reported.
     */
    private function rememberAppleRefreshToken(
        User $user,
        ?string $code,
        string $clientId,
        AppleTokenService $apple,
    ): void {
        if (! $code || ! $apple->isConfigured()) {
            return;
        }

        try {
            if ($refreshToken = $apple->exchangeAuthorizationCode($code, $clientId)) {
                $user->update(['apple_refresh_token' => $refreshToken]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Close the account from inside the app, as the app stores require.
     */
    public function destroy(Request $request, AppleTokenService $apple): JsonResponse
    {
        $user = $request->user();

        if ($user->password) {
            // Not the `current_password` rule: it resolves the user from the default
            // guard, which is not the Sanctum-authenticated user on an API request.
            $request->validate(['password' => ['required', 'string']]);

            if (! Hash::check((string) $request->input('password'), $user->password)) {
                throw ValidationException::withMessages(['password' => 'That password is incorrect.']);
            }
        } else {
            // Google-only accounts have no password, so they confirm by typing their email.
            $request->validate(['confirm_email' => ['required', 'string']]);

            if (Str::lower(trim((string) $request->input('confirm_email'))) !== Str::lower($user->email)) {
                throw ValidationException::withMessages([
                    'confirm_email' => 'Enter your email address exactly to confirm.',
                ]);
            }
        }

        // Apple requires the token to be revoked when the account goes, but their
        // servers must not be able to keep the user from leaving.
        try {
            $apple->revoke($user);
        } catch (\Throwable $e) {
            report($e);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Your account has been deleted.']);
    }

    private function tokenResponse(User $user, ?string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'Lectura Go')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
        ], $status);
    }
}
