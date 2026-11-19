<?php

namespace App\Actions;

use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Response;

class DeselectPrompt
{
    /**
     * Drop a prompt. The row and its text are kept so re-selecting restores them.
     */
    public function __invoke(Participant $participant, Prompt $prompt): void
    {
        Response::where('participant_id', $participant->id)
            ->where('prompt_id', $prompt->id)
            ->update(['is_selected' => false]);
    }
}
