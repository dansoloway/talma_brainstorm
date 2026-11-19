<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Response> */
class ResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'body' => fake()->paragraphs(2, true),
            'is_selected' => true,
            'last_saved_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Response $response) {
            $response->participant_id ??= Participant::factory()->create()->id;
            $prompt = $response->prompt_id ? Prompt::find($response->prompt_id) : Prompt::factory()->create();
            $response->prompt_id = $prompt->id;
            $response->stage_id = $prompt->stage_id;
            $response->prompt_body_snapshot ??= $prompt->body;
        });
    }

    /** Text that most often breaks CSV: Hebrew, quotes, commas, newlines. */
    public function trickyText(): static
    {
        return $this->state(fn () => [
            'body' => "שורה ראשונה, עם פסיק\nLine two with \"quotes\"\r\nשורה שלישית; end",
        ]);
    }
}
