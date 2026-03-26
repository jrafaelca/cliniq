<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Start a new attempt only with incorrect questions.
     */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();
        $activeAttempt = $this->activeAttemptFor($user);

        if ($activeAttempt !== null) {
            return to_route('practice.show', $activeAttempt);
        }

        $questionIds = $this->incorrectQuestionIdsQuery($user)
            ->inRandomOrder()
            ->pluck('questions.id')
            ->map(fn (int $id): int => $id)
            ->values()
            ->all();

        if ($questionIds === []) {
            return to_route('dashboard')
                ->with('review_error', __('review.no_incorrect_questions'));
        }

        $attempt = Attempt::query()->create([
            'user_id' => $user->id,
            'status' => Attempt::STATUS_ACTIVE,
            'question_ids' => $questionIds,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        return to_route('practice.show', $attempt);
    }

    private function activeAttemptFor(User $user): ?Attempt
    {
        return $user->attempts()
            ->where('status', Attempt::STATUS_ACTIVE)
            ->latest('started_at')
            ->first();
    }

    private function incorrectAnswersQuery(User $user): Builder
    {
        return Attempt::query()
            ->join('attempt_answers', 'attempt_answers.attempt_id', '=', 'attempts.id')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->where('attempts.user_id', $user->id)
            ->where('attempts.status', Attempt::STATUS_FINISHED)
            ->where('attempt_answers.is_correct', false)
            ->when(
                $user->subject_id !== null,
                fn (Builder $query) => $query->where('questions.subject_id', $user->subject_id),
            );
    }

    private function incorrectQuestionIdsQuery(User $user): Builder
    {
        return $this->incorrectAnswersQuery($user)
            ->select('questions.id')
            ->distinct();
    }
}
