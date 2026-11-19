<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Organizers (admin panel users). Participants are a separate model and guard.
 * Only the explicit admin email allowlist may sign in (via Google).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return list<string>
     */
    public static function adminEmails(): array
    {
        $configured = config('services.google.admin_emails', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($email) => strtolower(trim((string) $email)),
            $configured,
        ))));
    }

    public static function emailAllowed(?string $email): bool
    {
        if (! is_string($email) || $email === '') {
            return false;
        }

        return in_array(strtolower(trim($email)), static::adminEmails(), true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return static::emailAllowed($this->email);
    }
}
