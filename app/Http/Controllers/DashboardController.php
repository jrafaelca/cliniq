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

        return Inertia::render('Dashboard', [
            'activeAttemptId' => $activeAttempt?->id,
            'hasQuestions' => Question::query()->exists(),
            'practiceError' => $request->session()->get('practice_error'),
            'latestResult' => $latestResult,
        ]);
    }
}
