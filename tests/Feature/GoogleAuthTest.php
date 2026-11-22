<?php

use App\Models\Participant;
use App\Models\Stage;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $email, string $name = 'Talma Organizer', bool $verified = true): SocialiteUser
{
    $user = new SocialiteUser;
    $user->map([
        'id' => 'google-123',
        'nickname' => null,
        'name' => $name,
        'email' => $email,
        'avatar' => null,
    ]);
    $user->user = [
        'email_verified' => $verified,
    ];

    return $user;
}

function mockGoogle(SocialiteUser $googleUser): void
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($googleUser);
    $provider->shouldReceive('scopes')->andReturnSelf();
    $provider->shouldReceive('with')->andReturnSelf();
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('shows Google-only sign-in on the admin login page', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee(route('auth.google.redirect'), false)
        ->assertSee('Sign in with Google')
        ->assertDontSee('type="password"', false)
        ->assertDontSee('wire:submit="authenticate"', false);
});

it('redirects organizers to Google', function () {
    mockGoogle(fakeGoogleUser('daniel@talmaisrael.com'));

    $this->get(route('auth.google.redirect'))->assertRedirect();
});

it('creates and signs in an allowlisted admin on first Google login', function () {
    mockGoogle(fakeGoogleUser('daniel@talmaisrael.com', 'Daniel'));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('filament.admin.pages.dashboard'));

    $user = User::where('email', 'daniel@talmaisrael.com')->sole();
    expect($user->name)->toBe('Daniel')
        ->and($user->password)->toBeNull()
        ->and($user->canAccessPanel(filament()->getDefaultPanel()))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('rejects Google accounts that are not in the admin allowlist', function () {
    mockGoogle(fakeGoogleUser('someone@talmaisrael.com'));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'someone@talmaisrael.com')->exists())->toBeFalse();
    $this->assertGuest();
});

it('rejects unverified Google emails even when allowlisted', function () {
    mockGoogle(fakeGoogleUser('daniel@talmaisrael.com', verified: false));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('blocks panel access for users outside the admin allowlist', function () {
    $user = User::factory()->create(['email' => 'not-allowed@talmaisrael.com']);

    expect($user->canAccessPanel(filament()->getDefaultPanel()))->toBeFalse();
});

it('creates and signs in a domain participant on first Google login', function () {
    Stage::factory()->create();
    mockGoogle(fakeGoogleUser('writer@talmaisrael.com', 'Writer'));

    $this->withSession(['google_auth_intent' => 'participant'])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('participant.home'));

    $participant = Participant::where('email', 'writer@talmaisrael.com')->sole();
    expect($participant->name)->toBe('Writer');
    $this->assertAuthenticatedAs($participant, 'participant');
});

it('rejects participant Google accounts outside the allowed domain', function () {
    mockGoogle(fakeGoogleUser('someone@gmail.com'));

    $this->withSession(['google_auth_intent' => 'participant'])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('participant.login'))
        ->assertSessionHasErrors('email');

    expect(Participant::where('email', 'someone@gmail.com')->exists())->toBeFalse();
    $this->assertGuest('participant');
});

it('reuses an existing participant by email on Google login', function () {
    Stage::factory()->create();
    $existing = Participant::factory()->create([
        'name' => 'Old Name',
        'email' => 'writer@talmaisrael.com',
    ]);

    mockGoogle(fakeGoogleUser('writer@talmaisrael.com', 'New Name'));

    $this->withSession(['google_auth_intent' => 'participant'])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('participant.home'));

    expect(Participant::where('email', 'writer@talmaisrael.com')->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('New Name');
    $this->assertAuthenticatedAs($existing->fresh(), 'participant');
});

it('starts the participant Google redirect with a domain hint', function () {
    mockGoogle(fakeGoogleUser('writer@talmaisrael.com'));

    $this->get(route('auth.google.participant'))
        ->assertRedirect()
        ->assertSessionHas('google_auth_intent', 'participant');
});
