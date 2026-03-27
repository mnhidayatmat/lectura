<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
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

        Auth::login($user, remember: true);

        // Redirect to first tenant dashboard if user belongs to one
        $tenantUser = $user->tenantUsers()->where('is_active', true)->first();
        if ($tenantUser) {
            return redirect('/' . $tenantUser->tenant->slug . '/dashboard');
        }

        return redirect('/');
    }
}
