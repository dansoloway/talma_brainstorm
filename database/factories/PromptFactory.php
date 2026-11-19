<?php

namespace Database\Factories;

use App\Enums\Language;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stage_id' => Stage::factory(),
            'body' => fake()->sentence(10).'?',
            'language' => Language::English,
        ];
    }

    public function hebrew(): static
    {
        return $this->state(fn () => [
            'body' => fake()->randomElement([
                'ספרו על רגע שבו הרגשתם שייכות.',
                'מה הייתם משנים בבית הספר שלכם, ולמה?',
                'תארו אדם שהשפיע עליכם, "במילים שלכם".',
            ]),
            'language' => Language::Hebrew,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_hidden' => true]);
    }
}
