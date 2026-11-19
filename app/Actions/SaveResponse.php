<?php

namespace App\Actions;

use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Support\Carbon;

/**
 * The hot path: called every ~2 seconds per person while they type.
 * Keep it to one read and at most one write. No events, mail or extra lookups.
 *
 * @phpstan-type SaveResult array{saved_at: ?Carbon, applied: bool, body: string}
 */
class SaveResponse
{
    /**
     * @param  bool  $clear  true only when the person really emptied the box themselves
     * @param  int|null  $clientSeq  monotonic per-prompt sequence from the browser; older seqs are ignored
     * @return SaveResult
     */
    public function __invoke(Participant $participant, int $promptId, string $body, bool $clear = false, ?int $clientSeq = null): array
    {
        $response = Response::where('participant_id', $participant->id)
            ->where('prompt_id', $promptId)
            ->first();

        if ($response) {
            // Stale overlapping request (e.g. keepalive finished after a newer save).
            if ($clientSeq !== null && $clientSeq <= (int) $response->save_seq) {
                return $this->result($response->last_saved_at, false, (string) $response->body);
            }

            if ($response->body === $body) {
                if ($clientSeq !== null && $clientSeq > (int) $response->save_seq) {
                    Response::whereKey($response->id)->update(['save_seq' => $clientSeq]);
                }

                return $this->result($response->last_saved_at, false, (string) $response->body);
            }

            // Guard against a broken page load wiping real text with an empty box.
            if ($body === '' && ! $clear) {
                return $this->result($response->last_saved_at, false, (string) $response->body);
            }

            $now = now();
            $update = [
                'body' => $body,
                'last_saved_at' => $now,
                'updated_at' => $now,
            ];
            if ($clientSeq !== null) {
                $update['save_seq'] = $clientSeq;
            }

            Response::whereKey($response->id)->update($update);

            return $this->result($now, true, $body);
        }

        // No row yet (should be rare: the box only appears after selecting).
        // Never throw text away: keep it as an unselected response.
        if ($body === '') {
            return $this->result(null, false, '');
        }

        // Don't create from a stale seq if a newer row appeared mid-flight — unique catch below handles races.
        $prompt = Prompt::findOrFail($promptId);
        $now = now();
        $response = new Response(['body' => $body, 'is_selected' => false, 'last_saved_at' => $now]);
        $response->participant_id = $participant->id;
        $response->prompt_id = $prompt->id;
        $response->stage_id = $prompt->stage_id;
        $response->prompt_body_snapshot = $prompt->body;
        $response->save_seq = $clientSeq ?? 0;

        try {
            $response->save();
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $existing = Response::where('participant_id', $participant->id)
                ->where('prompt_id', $promptId)
                ->first();

            if ($existing && $clientSeq !== null && $clientSeq <= (int) $existing->save_seq) {
                return $this->result($existing->last_saved_at, false, (string) $existing->body);
            }

            $update = [
                'body' => $body,
                'last_saved_at' => $now,
                'updated_at' => $now,
            ];
            if ($clientSeq !== null) {
                $update['save_seq'] = $clientSeq;
            }

            Response::where('participant_id', $participant->id)->where('prompt_id', $promptId)->update($update);

            return $this->result($now, true, $body);
        }

        return $this->result($now, true, $body);
    }

    /**
     * @return SaveResult
     */
    private function result(?Carbon $savedAt, bool $applied, string $body): array
    {
        return [
            'saved_at' => $savedAt,
            'applied' => $applied,
            'body' => $body,
        ];
    }
}
