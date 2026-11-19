<?php

namespace App\Models;

use App\Enums\Language;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prompt extends Model
{
    use HasFactory;

    protected $fillable = ['stage_id', 'body', 'language', 'sort', 'is_hidden'];

    protected function casts(): array
    {
        return [
            'language' => Language::class,
            'is_hidden' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Prompt $prompt) {
            if (! $prompt->sort) {
                $prompt->sort = (int) static::where('stage_id', $prompt->stage_id)->max('sort') + 1;
            }
        });
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function hasResponses(): bool
    {
        return $this->responses()->exists();
    }
}
