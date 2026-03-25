<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Question;
use App\Models\Subject;
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
            'subject_id' => Subject::factory(),
            'category_id' => Category::factory(),
            'topic_id' => null,
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

    public function configure(): static
    {
        return $this->afterCreating(function (Question $question): void {
            $category = Category::query()->find($question->category_id);

            if ($category !== null && $question->subject_id !== $category->subject_id) {
                $question->update([
                    'subject_id' => $category->subject_id,
                ]);
            }
        });
    }
}
