<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Response extends Model
{
    use HasFactory;

    /** participant_id is deliberately NOT fillable: it always comes from the auth guard. */
    protected $fillable = ['body', 'answers', 'answer_save_seqs', 'is_selected', 'last_saved_at'];

    protected $attributes = [
        'body' => '',
        'is_selected' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'last_saved_at' => 'datetime',
            'answers' => 'array',
            'answer_save_seqs' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public function answersList(): array
    {
        $answers = $this->answers;

        if (is_array($answers) && $answers !== []) {
            $list = array_values(array_map(static fn ($answer) => (string) $answer, $answers));
            $hasAnswerText = array_filter($list, static fn (string $answer) => trim($answer) !== '') !== [];

            // Legacy rows / admin force-fills may update body without syncing answers.
            if (! $hasAnswerText && trim((string) $this->body) !== '') {
                return [(string) $this->body];
            }

            return $list;
        }

        return [(string) $this->body];
    }

    /**
     * @return list<int>
     */
    public function answerSaveSeqsList(): array
    {
        $seqs = $this->answer_save_seqs;

        if (! is_array($seqs)) {
            return [];
        }

        return array_values(array_map(static fn ($seq) => (int) $seq, $seqs));
    }

    public function hasText(): bool
    {
        foreach ($this->answersList() as $answer) {
            if (trim($answer) !== '') {
                return true;
            }
        }

        return false;
    }

    /** Flatten answers for CSV / admin tables. */
    public function displayBody(): string
    {
        $parts = array_values(array_filter(
            $this->answersList(),
            static fn (string $answer) => trim($answer) !== '',
        ));

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * @param  list<string>  $answers
     */
    public function setAnswersList(array $answers): void
    {
        $normalized = array_values(array_map(static fn ($answer) => (string) $answer, $answers));

        if ($normalized === []) {
            $normalized = [''];
        }

        $this->answers = $normalized;

        $parts = array_values(array_filter(
            $normalized,
            static fn (string $answer) => trim($answer) !== '',
        ));

        // Set body from the new answers only — do not call displayBody()/answersList()
        // here, or the legacy body fallback can resurrect cleared text.
        $this->body = implode("\n\n---\n\n", $parts);
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
