<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Response extends Model
{
    use HasFactory;

    /** participant_id is deliberately NOT fillable: it always comes from the auth guard. */
    protected $fillable = ['body', 'is_selected', 'last_saved_at'];

    protected $attributes = [
        'body' => '',
        'is_selected' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'last_saved_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
