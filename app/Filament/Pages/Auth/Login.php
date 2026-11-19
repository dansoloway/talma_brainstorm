<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Google-only admin login — no email/password form.
 */
class Login extends BaseLogin
{
    #[Unexposed]
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('auth.google-button'),
        ]);
    }

    #[Unexposed]
    public function getHeading(): string | Htmlable | null
    {
        return __('Sign in');
    }

    #[Unexposed]
    public function getSubheading(): string | Htmlable | null
    {
        return __('Use your Google account to open the admin panel.');
    }
}
