<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Participant extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['name'];

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
        });
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function link(): string
    {
        return route('participant.login', $this->access_token);
    }
}
