<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Category;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_dashboard_stats_route()
    {
        $this->get(route('dashboard.stats'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_stats_returns_summary_category_performance_and_incorrect_count()
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create();
        $firstCategory = Category::factory()->create([
            'subject_id' => $subject->id,
            'name' => 'Cardiología',
        ]);
        $secondCategory = Category::factory()->create([
            'subject_id' => $subject->id,
            'name' => 'Respiratorio',
        ]);

        $firstQuestion = Question::factory()->create([
            'subject_id' => $subject->id,
            'category_id' => $firstCategory->id,
            'topic_id' => null,
        ]);
        $secondQuestion = Question::factory()->create([
            'subject_id' => $subject->id,
            'category_id' => $secondCategory->id,
            'topic_id' => null,
        ]);

        $attemptOne = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [$firstQuestion->id],
            'score' => 80,
            'started_at' => now()->subMinutes(45),
            'finished_at' => now()->subMinutes(35),
        ]);

        $attemptTwo = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [$firstQuestion->id, $secondQuestion->id],
            'score' => 40,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(20),
        ]);

        Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_ACTIVE,
            'question_ids' => [$firstQuestion->id],
            'started_at' => now()->subMinutes(5),
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $attemptOne->id,
            'question_id' => $firstQuestion->id,
            'selected_options' => [],
            'is_correct' => true,
            'time_spent_seconds' => 300,
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $attemptTwo->id,
            'question_id' => $firstQuestion->id,
            'selected_options' => [],
            'is_correct' => false,
            'time_spent_seconds' => 300,
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $attemptTwo->id,
            'question_id' => $secondQuestion->id,
            'selected_options' => [],
            'is_correct' => false,
            'time_spent_seconds' => 300,
        ]);

        $response = $this->actingAs($user)->getJson(route('dashboard.stats'));

        $response->assertOk();
        $response->assertJsonPath('total_attempts', 2);
        $response->assertJsonPath('average_score', 60);
        $response->assertJsonPath('best_score', 80);
        $response->assertJsonPath('total_time', 15);
        $response->assertJsonPath('incorrect_count', 2);

        $this->assertSame($secondCategory->id, $response->json('category_performance.0.category_id'));
        $this->assertEquals(0.0, $response->json('category_performance.0.score'));
        $this->assertSame($firstCategory->id, $response->json('category_performance.1.category_id'));
        $this->assertEquals(50.0, $response->json('category_performance.1.score'));
    }

    public function test_dashboard_stats_limits_recent_attempts_to_five()
    {
        $user = User::factory()->create();

        foreach (range(1, 6) as $index) {
            Attempt::factory()->for($user)->create([
                'status' => Attempt::STATUS_FINISHED,
                'question_ids' => [],
                'score' => $index * 10,
                'started_at' => now()->subMinutes(20 + $index),
                'finished_at' => now()->subMinutes($index),
                'created_at' => now()->subMinutes($index),
                'updated_at' => now()->subMinutes($index),
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('dashboard.stats'));

        $response->assertOk();
        $this->assertCount(5, $response->json('recent_attempts'));
        $this->assertEquals(10.0, $response->json('recent_attempts.0.score'));
        $this->assertEquals(50.0, $response->json('recent_attempts.4.score'));
    }

    public function test_dashboard_stats_normalizes_short_duration_to_whole_minute()
    {
        $user = User::factory()->create();
        $question = Question::factory()->create();

        $attempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [$question->id],
            'score' => 75,
            'started_at' => now()->subSeconds(22),
            'finished_at' => now(),
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_options' => [],
            'is_correct' => true,
            'time_spent_seconds' => 22,
        ]);

        $response = $this->actingAs($user)->getJson(route('dashboard.stats'));

        $response->assertOk();
        $response->assertJsonPath('total_time', 1);
        $response->assertJsonPath('recent_attempts.0.duration', 1);
    }
}
