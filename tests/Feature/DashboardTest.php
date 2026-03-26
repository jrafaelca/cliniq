<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Category;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', app()->getLocale()),
            );
    }

    public function test_dashboard_has_questions_false_when_user_subject_has_no_questions()
    {
        $userSubject = Subject::factory()->create();
        $otherSubject = Subject::factory()->create();

        $otherCategory = Category::factory()->create([
            'subject_id' => $otherSubject->id,
        ]);

        Question::factory()->create([
            'subject_id' => $otherSubject->id,
            'category_id' => $otherCategory->id,
            'topic_id' => null,
        ]);

        $user = User::factory()->withSubject($userSubject->id)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasQuestions', false),
            );
    }

    public function test_dashboard_has_questions_true_for_user_without_subject()
    {
        $subject = Subject::factory()->create();
        $category = Category::factory()->create([
            'subject_id' => $subject->id,
        ]);

        Question::factory()->create([
            'subject_id' => $subject->id,
            'category_id' => $category->id,
            'topic_id' => null,
        ]);

        $user = User::factory()->create([
            'subject_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasQuestions', true),
            );
    }

    public function test_dashboard_exposes_remaining_questions_for_active_attempt()
    {
        $user = User::factory()->create();
        $question = Question::factory()->create();

        $attempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_ACTIVE,
            'question_ids' => [$question->id],
            'started_at' => now()->subMinutes(5),
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_options' => [QuestionOption::factory()->for($question)->create()->id],
            'is_correct' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeAttemptId', $attempt->id)
                ->where('activeAttemptRemainingQuestions', 0)
                ->where('activeAttemptHasProgress', true)
                ->where('activeAttemptMode', Attempt::MODE_PRACTICE),
            );
    }

    public function test_dashboard_marks_active_attempt_without_answers_as_no_progress(): void
    {
        $user = User::factory()->create();
        $question = Question::factory()->create();

        $attempt = Attempt::factory()->for($user)->create([
            'status' => Attempt::STATUS_ACTIVE,
            'question_ids' => [$question->id],
            'started_at' => now()->subMinutes(3),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeAttemptId', $attempt->id)
                ->where('activeAttemptHasProgress', false)
                ->where('activeAttemptMode', Attempt::MODE_PRACTICE),
            );
    }

    public function test_dashboard_exposes_review_error_flash_message()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                'review_error' => __('review.no_incorrect_questions'),
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviewError', __('review.no_incorrect_questions')),
            );
    }
}
