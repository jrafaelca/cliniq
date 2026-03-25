<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PracticeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_from_practice_routes()
    {
        $owner = User::factory()->create();
        $question = $this->createSingleQuestion();
        $attempt = $this->createActiveAttempt($owner, [$question->id]);

        $this->post(route('practice.start'))
            ->assertRedirect(route('login'));

        $this->get(route('practice.show', $attempt))
            ->assertRedirect(route('login'));

        $this->post(route('practice.answer', $attempt), [
            'question_id' => $question->id,
            'selected_options' => [$question->options->first()->id],
        ])->assertRedirect(route('login'));

        $this->post(route('practice.finish', $attempt))
            ->assertRedirect(route('login'));
    }

    public function test_start_creates_attempt_with_random_questions()
    {
        $user = User::factory()->create();

        for ($index = 0; $index < 12; $index++) {
            $this->createSingleQuestion();
        }

        $response = $this->actingAs($user)->post(route('practice.start'));

        $attempt = Attempt::query()->first();

        $this->assertNotNull($attempt);
        $this->assertSame(Attempt::STATUS_ACTIVE, $attempt->status);
        $this->assertNotNull($attempt->started_at);
        $this->assertCount(10, $attempt->question_ids);

        $response->assertRedirect(route('practice.show', $attempt));
    }

    public function test_start_resumes_active_attempt_if_exists()
    {
        $user = User::factory()->create();
        $question = $this->createSingleQuestion();

        $activeAttempt = $this->createActiveAttempt($user, [$question->id]);

        $response = $this->actingAs($user)->post(route('practice.start'));

        $response->assertRedirect(route('practice.show', $activeAttempt));
        $this->assertDatabaseCount('attempts', 1);
    }

    public function test_start_redirects_with_error_when_no_questions_exist()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('practice.start'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('practice_error');
    }

    public function test_show_returns_current_question_and_progress()
    {
        $user = User::factory()->create();

        $firstQuestion = $this->createSingleQuestion();
        $secondQuestion = $this->createSingleQuestion();
        $correctOptionId = $firstQuestion->options->firstWhere('is_correct', true)->id;

        $attempt = $this->createActiveAttempt($user, [$firstQuestion->id, $secondQuestion->id]);

        AttemptAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $firstQuestion->id,
            'selected_options' => [$correctOptionId],
            'is_correct' => true,
        ]);

        $this->actingAs($user)
            ->get(route('practice.show', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Practice/Question')
                ->where('attemptId', $attempt->id)
                ->where('question.id', $secondQuestion->id)
                ->where('progress.current', 2)
                ->where('progress.total', 2)
                ->where('progress.percent', 50),
            );
    }

    public function test_show_returns_result_view_when_attempt_is_finished()
    {
        $user = User::factory()->create();
        $question = $this->createSingleQuestion();
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;

        $attempt = Attempt::factory()
            ->for($user)
            ->finished(100)
            ->create([
                'question_ids' => [$question->id],
            ]);

        AttemptAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_options' => [$correctOptionId],
            'is_correct' => true,
        ]);

        $this->actingAs($user)
            ->get(route('practice.show', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Practice/Result')
                ->where('attemptId', $attempt->id)
                ->where('correct_count', 1)
                ->where('incorrect_count', 0)
                ->where('total_questions', 1),
            );
    }

    public function test_answer_evaluates_single_choice_correctly()
    {
        $user = User::factory()->create();
        $question = $this->createSingleQuestion();
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;
        $wrongOptionId = $question->options->firstWhere('is_correct', false)->id;

        $correctAttempt = $this->createActiveAttempt($user, [$question->id]);
        $wrongAttempt = $this->createActiveAttempt($user, [$question->id]);

        $this->actingAs($user)
            ->postJson(route('practice.answer', $correctAttempt), [
                'question_id' => $question->id,
                'selected_options' => [$correctOptionId],
            ])
            ->assertOk()
            ->assertJson([
                'is_correct' => true,
                'answered_count' => 1,
                'total_questions' => 1,
                'is_last_question' => true,
            ]);

        $this->actingAs($user)
            ->postJson(route('practice.answer', $wrongAttempt), [
                'question_id' => $question->id,
                'selected_options' => [$wrongOptionId],
            ])
            ->assertOk()
            ->assertJson([
                'is_correct' => false,
            ]);
    }

    public function test_answer_requires_exact_match_for_multiple_choice_questions()
    {
        $user = User::factory()->create();
        $question = $this->createMultipleQuestion();

        $correctOptionIds = $question->options
            ->where('is_correct', true)
            ->pluck('id')
            ->values()
            ->all();

        $incorrectOptionId = $question->options
            ->firstWhere('is_correct', false)
            ->id;

        $correctAttempt = $this->createActiveAttempt($user, [$question->id]);
        $incorrectAttempt = $this->createActiveAttempt($user, [$question->id]);

        $this->actingAs($user)
            ->postJson(route('practice.answer', $correctAttempt), [
                'question_id' => $question->id,
                'selected_options' => $correctOptionIds,
            ])
            ->assertOk()
            ->assertJson([
                'is_correct' => true,
            ]);

        $this->actingAs($user)
            ->postJson(route('practice.answer', $incorrectAttempt), [
                'question_id' => $question->id,
                'selected_options' => [$correctOptionIds[0], $incorrectOptionId],
            ])
            ->assertOk()
            ->assertJson([
                'is_correct' => false,
            ]);
    }

    public function test_answer_validates_question_scope_and_invalid_options()
    {
        $user = User::factory()->create();
        $attemptQuestion = $this->createSingleQuestion();
        $outsideQuestion = $this->createSingleQuestion();

        $outsideOptionId = $outsideQuestion->options->first()->id;
        $attempt = $this->createActiveAttempt($user, [$attemptQuestion->id]);

        $this->actingAs($user)
            ->postJson(route('practice.answer', $attempt), [
                'question_id' => $outsideQuestion->id,
                'selected_options' => [$outsideOptionId],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question_id']);

        $this->actingAs($user)
            ->postJson(route('practice.answer', $attempt), [
                'question_id' => $attemptQuestion->id,
                'selected_options' => [$outsideOptionId],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selected_options']);
    }

    public function test_answer_cannot_be_submitted_twice_for_the_same_question()
    {
        $user = User::factory()->create();
        $question = $this->createSingleQuestion();
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;

        $attempt = $this->createActiveAttempt($user, [$question->id]);

        $payload = [
            'question_id' => $question->id,
            'selected_options' => [$correctOptionId],
        ];

        $this->actingAs($user)
            ->postJson(route('practice.answer', $attempt), $payload)
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('practice.answer', $attempt), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attempt']);
    }

    public function test_finish_marks_attempt_as_finished_and_calculates_score()
    {
        $user = User::factory()->create();
        $firstQuestion = $this->createSingleQuestion();
        $secondQuestion = $this->createSingleQuestion();

        $firstCorrectOptionId = $firstQuestion->options->firstWhere('is_correct', true)->id;
        $secondWrongOptionId = $secondQuestion->options->firstWhere('is_correct', false)->id;

        $attempt = $this->createActiveAttempt($user, [$firstQuestion->id, $secondQuestion->id]);

        AttemptAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $firstQuestion->id,
            'selected_options' => [$firstCorrectOptionId],
            'is_correct' => true,
        ]);

        AttemptAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $secondQuestion->id,
            'selected_options' => [$secondWrongOptionId],
            'is_correct' => false,
        ]);

        $this->actingAs($user)
            ->post(route('practice.finish', $attempt))
            ->assertRedirect(route('practice.show', $attempt));

        $attempt->refresh();

        $this->assertSame(Attempt::STATUS_FINISHED, $attempt->status);
        $this->assertSame('50.00', $attempt->score);
        $this->assertNotNull($attempt->finished_at);

        $this->actingAs($user)
            ->get(route('practice.show', $attempt))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Practice/Result')
                ->where('correct_count', 1)
                ->where('incorrect_count', 1)
                ->where('total_questions', 2),
            );
    }

    public function test_user_cannot_access_another_users_attempt()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $question = $this->createSingleQuestion();
        $correctOptionId = $question->options->firstWhere('is_correct', true)->id;
        $attempt = $this->createActiveAttempt($owner, [$question->id]);

        $this->actingAs($intruder)
            ->get(route('practice.show', $attempt))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->postJson(route('practice.answer', $attempt), [
                'question_id' => $question->id,
                'selected_options' => [$correctOptionId],
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('practice.finish', $attempt))
            ->assertForbidden();
    }

    private function createActiveAttempt(User $user, array $questionIds): Attempt
    {
        return Attempt::factory()
            ->for($user)
            ->create([
                'status' => Attempt::STATUS_ACTIVE,
                'question_ids' => $questionIds,
                'started_at' => now(),
                'finished_at' => null,
                'score' => null,
            ]);
    }

    private function createSingleQuestion(): Question
    {
        $question = Question::factory()->single()->create();

        QuestionOption::factory()->for($question)->correct()->create([
            'text' => 'Correcta',
        ]);
        QuestionOption::factory()->for($question)->create([
            'text' => 'Incorrecta',
            'is_correct' => false,
        ]);
        QuestionOption::factory()->for($question)->create([
            'text' => 'Distractor',
            'is_correct' => false,
        ]);

        return $question->fresh('options');
    }

    private function createMultipleQuestion(): Question
    {
        $question = Question::factory()->multiple()->create();

        QuestionOption::factory()->for($question)->correct()->create([
            'text' => 'Correcta 1',
        ]);
        QuestionOption::factory()->for($question)->correct()->create([
            'text' => 'Correcta 2',
        ]);
        QuestionOption::factory()->for($question)->create([
            'text' => 'Incorrecta',
            'is_correct' => false,
        ]);

        return $question->fresh('options');
    }
}
