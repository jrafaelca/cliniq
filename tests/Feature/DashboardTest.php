<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Question;
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
        $response->assertOk();
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
}
