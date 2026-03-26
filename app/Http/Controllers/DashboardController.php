<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $activeAttempt = $user->attempts()
            ->where('status', Attempt::STATUS_ACTIVE)
            ->latest('started_at')
            ->first();

        $latestFinishedAttempt = $user->attempts()
            ->where('status', Attempt::STATUS_FINISHED)
            ->latest('finished_at')
            ->first();

        $latestResult = null;
        $activeAttemptRemainingQuestions = null;
        $activeAttemptHasProgress = false;

        if ($activeAttempt !== null) {
            $answeredCount = $activeAttempt->answers()->count();
            $totalQuestions = count($activeAttempt->question_ids ?? []);

            $activeAttemptRemainingQuestions = max(0, $totalQuestions - $answeredCount);
            $activeAttemptHasProgress = $answeredCount > 0;
        }

        if ($latestFinishedAttempt !== null) {
            $totalQuestions = count($latestFinishedAttempt->question_ids ?? []);
            $correctCount = $latestFinishedAttempt->answers()->where('is_correct', true)->count();

            $latestResult = [
                'attemptId' => $latestFinishedAttempt->id,
                'score' => $latestFinishedAttempt->score,
                'correct_count' => $correctCount,
                'incorrect_count' => max(0, $totalQuestions - $correctCount),
                'total_questions' => $totalQuestions,
                'finished_at' => $latestFinishedAttempt->finished_at,
            ];
        }

        $questionsQuery = Question::query();

        if ($user->subject_id !== null) {
            $questionsQuery->where('subject_id', $user->subject_id);
        }

        return Inertia::render('Dashboard', [
            'activeAttemptId' => $activeAttempt?->id,
            'activeAttemptRemainingQuestions' => $activeAttemptRemainingQuestions,
            'activeAttemptHasProgress' => $activeAttemptHasProgress,
            'activeAttemptMode' => $activeAttempt?->mode,
            'hasQuestions' => $questionsQuery->exists(),
            'practiceError' => $request->session()->get('practice_error'),
            'reviewError' => $request->session()->get('review_error'),
            'latestResult' => $latestResult,
        ]);
    }
}
