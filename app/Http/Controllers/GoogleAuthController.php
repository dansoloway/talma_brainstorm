<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => __('Google sign-in expired. Please try again.')]);
        } catch (\Throwable) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => __('Google sign-in failed. Please try again.')]);
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));

        if ($email === '' || ! User::emailAllowed($email)) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => __('You are not an admin of this platform.')]);
        }

        $verified = $googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? true;
        if (! $verified) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => __('Your Google email must be verified.')]);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: strstr($email, '@', true) ?: $email,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => null,
            ]);
        } else {
            $user->forceFill([
                'name' => $googleUser->getName() ?: $user->name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        Auth::guard('web')->login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('filament.admin.pages.dashboard'));
    }
}
