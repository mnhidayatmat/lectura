<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /** Deep link the mobile app listens on to receive the one-time login code. */
    public const MOBILE_CALLBACK_URL = 'lecturago://auth';

    public static function mobileCodeCacheKey(string $code): string
    {
        return 'mobile_google_auth:'.hash('sha256', $code);
    }

    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->forget('google_mobile');

        return Socialite::driver('google')->redirect();
    }

    public function redirectMobile(Request $request): RedirectResponse
    {
        $request->session()->put('google_mobile', true);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $mobile = (bool) $request->session()->pull('google_mobile', false);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            if ($mobile) {
                return redirect()->away(self::MOBILE_CALLBACK_URL.'?error=google_failed');
            }

            return redirect()->route('login')->with('error', 'Google authentication failed. Please try again.');
        }

        // Find by google_id first, then by email
        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Link existing email account to Google
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);
            }
        } else {
            // Update avatar on each login
            $user->update(['avatar_url' => $googleUser->getAvatar()]);
        }

        // Mobile app: hand back a short-lived one-time code, exchanged for an API token
        if ($mobile) {
            $code = Str::random(64);
            Cache::put(self::mobileCodeCacheKey($code), $user->id, now()->addMinutes(2));

            return redirect()->away(self::MOBILE_CALLBACK_URL.'?code='.$code);
        }

        Auth::login($user, remember: true);

        // Super admin always goes to admin panel
        if ($user->is_super_admin) {
            return redirect('/admin');
        }

        // Redirect to first tenant dashboard if user belongs to one
        $tenantUser = $user->tenantUsers()->where('is_active', true)->first();
        if ($tenantUser) {
            return redirect('/' . $tenantUser->tenant->slug . '/dashboard');
        }

        // New user with no tenant — onboarding
        return redirect()->route('onboarding');
    }
}
