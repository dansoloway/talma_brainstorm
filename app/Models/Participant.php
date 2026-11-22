<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Participant extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['name', 'email'];

    protected $hidden = ['access_token', 'remember_token'];

    protected $rememberTokenName = false;

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Participant $participant) {
            $participant->access_token ??= Str::random(40);

            if (is_string($participant->email) && $participant->email !== '') {
                $participant->email = strtolower(trim($participant->email));
            }
        });

        static::saving(function (Participant $participant) {
            if (is_string($participant->email) && $participant->email !== '') {
                $participant->email = strtolower(trim($participant->email));
            }
        });
    }

    public static function allowedDomain(): string
    {
        return strtolower(trim((string) config('services.google.allowed_domain', 'talmaisrael.com')));
    }

    public static function emailAllowed(?string $email): bool
    {
        if (! is_string($email) || $email === '') {
            return false;
        }

        $email = strtolower(trim($email));
        $domain = static::allowedDomain();

        return $domain !== '' && str_ends_with($email, '@'.$domain);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }
}
