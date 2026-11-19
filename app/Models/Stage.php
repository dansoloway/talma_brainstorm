<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'sort'];

    protected static function booted(): void
    {
        static::creating(function (Stage $stage) {
            if (! $stage->sort) {
                $stage->sort = (int) static::max('sort') + 1;
            }
        });
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class)->orderBy('sort')->orderBy('id');
    }

    public function visiblePrompts(): HasMany
    {
        return $this->prompts()->where('is_hidden', false);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /** 1-based position of this stage among all stages. */
    public function number(): int
    {
        return static::ordered()->pluck('id')->search($this->id) + 1;
    }
}
