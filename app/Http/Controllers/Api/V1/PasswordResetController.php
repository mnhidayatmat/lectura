<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * Password reset for the mobile app. The web emails a link to its own reset page;
 * the app instead emails a six-digit code that is typed back in, so the whole
 * flow stays on the phone.
 */
class PasswordResetController extends Controller
{
    private const MINUTES = 15;

    private const MAX_ATTEMPTS = 5;

    public function sendCode(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::where('email', Str::lower(trim($request->string('email')->toString())))->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            Cache::put(self::cacheKey($user->email), [
                'hash' => Hash::make($code),
                'attempts' => 0,
            ], now()->addMinutes(self::MINUTES));

            $user->notify(new PasswordResetCode($code, self::MINUTES));
        }

        // The same answer either way, so the endpoint cannot be used to find accounts.
        return response()->json([
            'message' => 'If an account uses that email, a reset code is on its way.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = Str::lower(trim($request->string('email')->toString()));
        $key = self::cacheKey($email);
        $entry = Cache::get($key);
        $user = User::where('email', $email)->first();

        if (! $user || ! is_array($entry) || $entry['attempts'] >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Ask for a new one.']);
        }

        if (! Hash::check(preg_replace('/\D/', '', $request->string('code')->toString()), $entry['hash'])) {
            $entry['attempts']++;
            Cache::put($key, $entry, now()->addMinutes(self::MINUTES));

            throw ValidationException::withMessages(['code' => 'That code is not right. Check the email and try again.']);
        }

        Cache::forget($key);

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'remember_token' => Str::random(60),
        ])->save();

        // As on the web: a reset signs every existing device out.
        $user->tokens()->delete();

        event(new PasswordReset($user));

        $token = $user->createToken($request->input('device_name') ?: 'Lectura Go')->plainTextToken;

        return response()->json([
            'message' => 'Your password has been reset.',
            'data' => ['token' => $token, 'user' => new UserResource($user)],
        ]);
    }

    private static function cacheKey(string $email): string
    {
        return 'mobile-password-reset:'.sha1(Str::lower($email));
    }
}
