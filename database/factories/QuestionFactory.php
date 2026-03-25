<?php

namespace Database\Factories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statement' => fake()->sentence(),
            'explanation' => fake()->sentence(),
            'type' => fake()->randomElement([Question::TYPE_SINGLE, Question::TYPE_MULTIPLE]),
        ];
    }

    public function single(): static
    {
        return $this->state(fn () => ['type' => Question::TYPE_SINGLE]);
    }

    public function multiple(): static
    {
        return $this->state(fn () => ['type' => Question::TYPE_MULTIPLE]);
    }
}
