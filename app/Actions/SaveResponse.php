<?php

namespace App\Actions;

use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The hot path: called every ~2 seconds per person while they type.
 * Keep it to one read and at most one write. No events, mail or extra lookups.
 *
 * @phpstan-type SaveResult array{saved_at: ?Carbon, applied: bool, body: string, answers: list<string>}
 */
class SaveResponse
{
    public const MAX_ANSWERS = 5;

    /**
     * @param  bool  $clear  true only when the person really emptied the box themselves
     * @param  int|null  $clientSeq  monotonic per-answer sequence from the browser; older seqs are ignored
     * @return SaveResult
     */
    public function __invoke(
        Participant $participant,
        int $promptId,
        string $body,
        bool $clear = false,
        ?int $clientSeq = null,
        int $answerIndex = 0,
    ): array {
        if ($answerIndex < 0 || $answerIndex >= self::MAX_ANSWERS) {
            throw ValidationException::withMessages([
                'answer_index' => __('You can add up to :max answers for one prompt.', ['max' => self::MAX_ANSWERS]),
            ]);
        }

        $response = Response::where('participant_id', $participant->id)
            ->where('prompt_id', $promptId)
            ->first();

        if ($response) {
            $answers = $response->answersList();
            $seqs = $response->answerSaveSeqsList();

            while (count($answers) <= $answerIndex) {
                $answers[] = '';
            }
            while (count($seqs) <= $answerIndex) {
                $seqs[] = 0;
            }

            $current = $answers[$answerIndex];

            // Stale overlapping request for this answer box.
            if ($clientSeq !== null && $clientSeq <= $seqs[$answerIndex]) {
                return $this->result($response->last_saved_at, false, $current, $answers);
            }

            if ($current === $body) {
                if ($clientSeq !== null && $clientSeq > $seqs[$answerIndex]) {
                    $seqs[$answerIndex] = $clientSeq;
                    Response::whereKey($response->id)->update([
                        'answer_save_seqs' => $seqs,
                        'save_seq' => max($seqs),
                    ]);
                }

                return $this->result($response->last_saved_at, false, $current, $answers);
            }

            // Guard against a broken page load wiping real text with an empty box.
            if ($body === '' && ! $clear) {
                return $this->result($response->last_saved_at, false, $current, $answers);
            }

            $answers[$answerIndex] = $body;
            if ($clientSeq !== null) {
                $seqs[$answerIndex] = $clientSeq;
            }

            $response->setAnswersList($answers);
            $response->answer_save_seqs = $seqs;
            $response->save_seq = max($seqs);
            $response->last_saved_at = now();
            $response->save();

            return $this->result($response->last_saved_at, true, $body, $response->answersList());
        }

        // No row yet (should be rare: the box only appears after selecting).
        if ($body === '' && $answerIndex === 0) {
            return $this->result(null, false, '', ['']);
        }

        $prompt = Prompt::findOrFail($promptId);
        $answers = array_fill(0, $answerIndex + 1, '');
        $answers[$answerIndex] = $body;
        $seqs = array_fill(0, $answerIndex + 1, 0);
        if ($clientSeq !== null) {
            $seqs[$answerIndex] = $clientSeq;
        }

        $now = now();
        $response = new Response(['is_selected' => false, 'last_saved_at' => $now]);
        $response->participant_id = $participant->id;
        $response->prompt_id = $prompt->id;
        $response->stage_id = $prompt->stage_id;
        $response->prompt_body_snapshot = $prompt->body;
        $response->prompt_details_snapshot = $prompt->details;
        $response->setAnswersList($answers);
        $response->answer_save_seqs = $seqs;
        $response->save_seq = max($seqs);

        try {
            $response->save();
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return $this->__invoke($participant, $promptId, $body, $clear, $clientSeq, $answerIndex);
        }

        return $this->result($now, true, $body, $response->answersList());
    }

    /**
     * @param  list<string>  $answers
     * @return SaveResult
     */
    private function result(?Carbon $savedAt, bool $applied, string $body, array $answers): array
    {
        return [
            'saved_at' => $savedAt,
            'applied' => $applied,
            'body' => $body,
            'answers' => array_values($answers),
        ];
    }
}
