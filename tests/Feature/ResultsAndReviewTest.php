<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Category;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResultsAndReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_from_results_and_review_start_routes()
    {
        $this->get(route('results.index'))
            ->assertRedirect(route('login'));

        $this->post(route('review.start'))
            ->assertRedirect(route('login'));
    }

    public function test_results_only_lists_finished_attempts()
    {
        $user = User::factory()->create();

        Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [],
            'score' => 70,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(10),
        ]);

        Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [],
            'score' => 90,
            'started_at' => now()->subMinutes(20),
            'finished_at' => now()->subMinutes(5),
        ]);

        Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_ACTIVE,
            'question_ids' => [],
            'started_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($user)
            ->get(route('results.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Results/Index')
                ->where('results.meta.total', 2)
                ->has('results.data', 2),
            );
    }

    public function test_results_normalizes_short_duration_to_whole_minute()
    {
        $user = User::factory()->create();
        $question = Question::factory()->create();

        $attempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [$question->id],
            'score' => 90,
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

        $this->actingAs($user)
            ->get(route('results.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.data.0.duration', 1),
            );
    }

    public function test_review_start_creates_attempt_with_all_unique_incorrect_questions()
    {
        $subject = Subject::factory()->create();
        $category = Category::factory()->create([
            'subject_id' => $subject->id,
        ]);
        $user = User::factory()->create();

        $questionIds = Question::factory()
            ->count(12)
            ->create([
                'subject_id' => $subject->id,
                'category_id' => $category->id,
                'topic_id' => null,
            ])
            ->pluck('id')
            ->all();

        $attempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => $questionIds,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now()->subMinutes(10),
            'score' => 0,
        ]);

        foreach ($questionIds as $questionId) {
            AttemptAnswer::factory()->create([
                'attempt_id' => $attempt->id,
                'question_id' => $questionId,
                'selected_options' => [],
                'is_correct' => false,
            ]);
        }

        $secondAttempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [$questionIds[0]],
            'started_at' => now()->subMinutes(9),
            'finished_at' => now()->subMinutes(5),
            'score' => 0,
        ]);

        // Repeat one failed question in another finished attempt to verify uniqueness by question_id.
        AttemptAnswer::factory()->create([
            'attempt_id' => $secondAttempt->id,
            'question_id' => $questionIds[0],
            'selected_options' => [],
            'is_correct' => false,
        ]);

        $response = $this->actingAs($user)->post(route('review.start'));

        $newAttempt = Attempt::query()
            ->where('status', Attempt::STATUS_ACTIVE)
            ->latest('id')
            ->first();

        $this->assertNotNull($newAttempt);
        $this->assertSame(Attempt::MODE_REVIEW, $newAttempt->mode);
        $this->assertNull($newAttempt->time_limit_seconds);
        $this->assertCount(12, $newAttempt->question_ids);
        $this->assertEqualsCanonicalizing($questionIds, $newAttempt->question_ids);
        $response->assertRedirect(route('practice.show', $newAttempt));
    }

    public function test_review_start_filters_incorrect_questions_by_user_subject()
    {
        $medicine = Subject::factory()->create();
        $technical = Subject::factory()->create();

        $medicineCategory = Category::factory()->create([
            'subject_id' => $medicine->id,
            'name' => 'Cardiología',
        ]);
        $technicalCategory = Category::factory()->create([
            'subject_id' => $technical->id,
            'name' => 'General',
        ]);

        $medicineQuestion = Question::factory()->create([
            'subject_id' => $medicine->id,
            'category_id' => $medicineCategory->id,
            'topic_id' => null,
        ]);
        $technicalQuestion = Question::factory()->create([
            'subject_id' => $technical->id,
            'category_id' => $technicalCategory->id,
            'topic_id' => null,
        ]);

        $user = User::factory()->withSubject($medicine->id)->create();

        $attempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [$medicineQuestion->id, $technicalQuestion->id],
            'started_at' => now()->subMinutes(20),
            'finished_at' => now()->subMinutes(10),
            'score' => 0,
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $medicineQuestion->id,
            'selected_options' => [],
            'is_correct' => false,
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $technicalQuestion->id,
            'selected_options' => [],
            'is_correct' => false,
        ]);

        $response = $this->actingAs($user)->post(route('review.start'));

        $newAttempt = Attempt::query()
            ->where('status', Attempt::STATUS_ACTIVE)
            ->latest('id')
            ->first();

        $this->assertNotNull($newAttempt);
        $this->assertSame(Attempt::MODE_REVIEW, $newAttempt->mode);
        $this->assertNull($newAttempt->time_limit_seconds);
        $this->assertSame([$medicineQuestion->id], $newAttempt->question_ids);
        $response->assertRedirect(route('practice.show', $newAttempt));
    }

    public function test_review_start_requires_restart_flag_when_active_attempt_exists()
    {
        $subject = Subject::factory()->create();
        $category = Category::factory()->create([
            'subject_id' => $subject->id,
        ]);
        $user = User::factory()->create();

        $incorrectQuestion = Question::factory()->create([
            'subject_id' => $subject->id,
            'category_id' => $category->id,
            'topic_id' => null,
        ]);

        $finishedAttempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [$incorrectQuestion->id],
            'started_at' => now()->subMinutes(12),
            'finished_at' => now()->subMinutes(10),
            'score' => 0,
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $finishedAttempt->id,
            'question_id' => $incorrectQuestion->id,
            'selected_options' => [],
            'is_correct' => false,
        ]);

        $activeAttempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_ACTIVE,
            'question_ids' => [$incorrectQuestion->id],
            'started_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($user)
            ->post(route('review.start'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('review_error', __('review.active_attempt_requires_restart'));

        $activeAttempt->refresh();
        $this->assertSame(Attempt::STATUS_ACTIVE, $activeAttempt->status);
    }

    public function test_review_start_with_restart_flag_expires_active_attempt_and_creates_review_attempt(): void
    {
        $subject = Subject::factory()->create();
        $category = Category::factory()->create([
            'subject_id' => $subject->id,
        ]);
        $user = User::factory()->create();

        $incorrectQuestion = Question::factory()->create([
            'subject_id' => $subject->id,
            'category_id' => $category->id,
            'topic_id' => null,
        ]);

        $finishedAttempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_FINISHED,
            'question_ids' => [$incorrectQuestion->id],
            'started_at' => now()->subMinutes(12),
            'finished_at' => now()->subMinutes(10),
            'score' => 0,
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $finishedAttempt->id,
            'question_id' => $incorrectQuestion->id,
            'selected_options' => [],
            'is_correct' => false,
        ]);

        $activeAttempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_ACTIVE,
            'question_ids' => [$incorrectQuestion->id],
            'started_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($user)->post(route('review.start'), [
            'restart' => 1,
        ]);

        $activeAttempt->refresh();
        $this->assertSame(Attempt::STATUS_EXPIRED, $activeAttempt->status);

        $newAttempt = Attempt::query()
            ->where('user_id', $user->id)
            ->where('status', Attempt::STATUS_ACTIVE)
            ->latest('id')
            ->first();

        $this->assertNotNull($newAttempt);
        $this->assertSame(Attempt::MODE_REVIEW, $newAttempt->mode);
        $this->assertSame([$incorrectQuestion->id], $newAttempt->question_ids);
        $response->assertRedirect(route('practice.show', $newAttempt));
    }

    public function test_review_start_redirects_with_error_when_user_has_no_incorrect_questions()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('review.start'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('review_error', __('review.no_incorrect_questions'));
    }
}
