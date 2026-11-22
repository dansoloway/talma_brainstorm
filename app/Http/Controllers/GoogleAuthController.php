<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    private const INTENT_ADMIN = 'admin';

    private const INTENT_PARTICIPANT = 'participant';

    public function redirectAdmin(): RedirectResponse
    {
        return $this->redirectToGoogle(self::INTENT_ADMIN);
    }

    public function redirectParticipant(): RedirectResponse
    {
        return $this->redirectToGoogle(self::INTENT_PARTICIPANT);
    }

    public function callback(): RedirectResponse
    {
        $intent = session()->pull('google_auth_intent', self::INTENT_ADMIN);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->fail($intent, __('Google sign-in expired. Please try again.'));
        } catch (\Throwable) {
            return $this->fail($intent, __('Google sign-in failed. Please try again.'));
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        $verified = $googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? true;

        if ($email === '') {
            return $this->fail($intent, __('Google sign-in failed. Please try again.'));
        }

        if (! $verified) {
            return $this->fail($intent, __('Your Google email must be verified.'));
        }

        if ($intent === self::INTENT_PARTICIPANT) {
            return $this->loginParticipant($googleUser, $email);
        }

        return $this->loginAdmin($googleUser, $email);
    }

    private function redirectToGoogle(string $intent): RedirectResponse
    {
        session(['google_auth_intent' => $intent]);

        $domain = (string) config('services.google.allowed_domain', 'talmaisrael.com');

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with([
                'prompt' => 'select_account',
                'hd' => $domain,
            ])
            ->redirect();
    }

    private function loginAdmin(object $googleUser, string $email): RedirectResponse
    {
        if (! User::emailAllowed($email)) {
            return $this->fail(self::INTENT_ADMIN, __('You are not an admin of this platform.'));
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

    private function loginParticipant(object $googleUser, string $email): RedirectResponse
    {
        if (! Participant::emailAllowed($email)) {
            return $this->fail(
                self::INTENT_PARTICIPANT,
                __('Sign in with your @:domain Google account.', [
                    'domain' => config('services.google.allowed_domain', 'talmaisrael.com'),
                ]),
            );
        }

        $name = $googleUser->getName() ?: strstr($email, '@', true) ?: $email;

        $participant = Participant::query()->where('email', $email)->first();

        if (! $participant) {
            $participant = Participant::create([
                'name' => $name,
                'email' => $email,
            ]);
        } else {
            $participant->forceFill([
                'name' => $name ?: $participant->name,
            ])->save();
        }

        Auth::guard('participant')->login($participant);
        request()->session()->regenerate();

        return redirect()->intended(route('participant.home'));
    }

    private function fail(string $intent, string $message): RedirectResponse
    {
        $route = $intent === self::INTENT_PARTICIPANT
            ? 'participant.login'
            : 'filament.admin.auth.login';

        return redirect()->route($route)->withErrors(['email' => $message]);
    }
}
