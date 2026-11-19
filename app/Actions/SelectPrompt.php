<?php

namespace App\Actions;

use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SelectPrompt
{
    public const MAX_PER_STAGE = 5;

    /**
     * Mark a prompt as selected for this participant. Re-selecting a prompt
     * that was dropped earlier brings its old text back.
     */
    public function __invoke(Participant $participant, Prompt $prompt): Response
    {
        if ($prompt->is_hidden) {
            throw ValidationException::withMessages(['prompt' => __('This prompt is not available.')]);
        }

        return DB::transaction(function () use ($participant, $prompt) {
            // Serialize selections per participant so two quick clicks can't both pass the limit.
            Participant::whereKey($participant->id)->lockForUpdate()->first();

            $response = Response::where('participant_id', $participant->id)
                ->where('prompt_id', $prompt->id)
                ->first();

            if ($response?->is_selected) {
                return $response;
            }

            // Count every selected row on this stage, including hidden prompts, so
            // hide → select another → unhide cannot push a person past the max.
            $selected = Response::where('participant_id', $participant->id)
                ->where('responses.stage_id', $prompt->stage_id)
                ->where('is_selected', true)
                ->count();

            if ($selected >= self::MAX_PER_STAGE) {
                throw ValidationException::withMessages([
                    'prompt' => __('You can choose up to :max prompts. Drop one before picking another.', ['max' => self::MAX_PER_STAGE]),
                ]);
            }

            if ($response) {
                $response->is_selected = true;
                $response->save();

                return $response;
            }

            $response = new Response(['is_selected' => true, 'body' => '']);
            $response->participant_id = $participant->id;
            $response->prompt_id = $prompt->id;
            $response->stage_id = $prompt->stage_id;
            $response->prompt_body_snapshot = $prompt->body;
            $response->save();

            return $response;
        });
    }
}
