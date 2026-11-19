<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class StageFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => fake()->sentence(3)];
    }
}
