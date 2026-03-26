<?php

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => Attempt::STATUS_ACTIVE,
            'question_ids' => [],
            'started_at' => now(),
            'last_activity_at' => now(),
            'finished_at' => null,
            'score' => null,
        ];
    }

    public function finished(?float $score = null): static
    {
        return $this->state(fn () => [
            'status' => Attempt::STATUS_FINISHED,
            'finished_at' => now(),
            'score' => $score ?? fake()->randomFloat(2, 0, 100),
        ]);
    }
}
