<?php

namespace App\Actions\Dashboard;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class DashboardStatsService
{
    /**
     * Build dashboard stats for a user.
     *
     * @return array{
     *     total_attempts: int,
     *     average_score: float,
     *     best_score: float,
     *     total_time: int,
     *     category_performance: array<int, array{category_id: int, name: string, score: float}>,
     *     incorrect_count: int,
     *     recent_attempts: array<int, array{id: int, score: float, created_at: string|null, duration: int}>
     * }
     */
    public function forUser(User $user): array
    {
        $hasCategoryPerformanceSchema = Schema::hasTable('questions')
            && Schema::hasTable('categories')
            && Schema::hasColumn('questions', 'category_id');

        $finishedAttempts = Attempt::query()
            ->whereBelongsTo($user)
            ->where('status', Attempt::STATUS_FINISHED)
            ->get(['id', 'score', 'started_at', 'finished_at', 'created_at']);

        $totalAttempts = $finishedAttempts->count();
        $averageScore = $totalAttempts > 0
            ? round((float) $finishedAttempts->avg(fn (Attempt $attempt): float => (float) ($attempt->score ?? 0)), 2)
            : 0.0;
        $bestScore = $totalAttempts > 0
            ? round((float) $finishedAttempts->max(fn (Attempt $attempt): float => (float) ($attempt->score ?? 0)), 2)
            : 0.0;
        $totalTime = (int) $finishedAttempts->sum(fn (Attempt $attempt): int => $this->durationInMinutes($attempt));

        $categoryPerformance = [];

        if ($hasCategoryPerformanceSchema) {
            $categoryPerformance = AttemptAnswer::query()
                ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
                ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
                ->join('categories', 'categories.id', '=', 'questions.category_id')
                ->where('attempts.user_id', $user->id)
                ->where('attempts.status', Attempt::STATUS_FINISHED)
                ->selectRaw('categories.id AS category_id')
                ->selectRaw('categories.name AS category_name')
                ->selectRaw('COUNT(*) AS total_answers')
                ->selectRaw('SUM(CASE WHEN attempt_answers.is_correct = 1 THEN 1 ELSE 0 END) AS correct_answers')
                ->groupBy('categories.id', 'categories.name')
                ->get()
                ->map(function (object $row): array {
                    $totalAnswers = (int) $row->total_answers;
                    $correctAnswers = (int) $row->correct_answers;

                    $score = $totalAnswers > 0
                        ? round(($correctAnswers / $totalAnswers) * 100, 2)
                        : 0.0;

                    return [
                        'category_id' => (int) $row->category_id,
                        'name' => (string) $row->category_name,
                        'score' => $score,
                    ];
                })
                ->sortBy('score')
                ->values()
                ->all();
        }

        $incorrectCount = AttemptAnswer::query()
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->where('attempts.user_id', $user->id)
            ->where('attempts.status', Attempt::STATUS_FINISHED)
            ->where('attempt_answers.is_correct', false)
            ->count();

        $recentAttempts = Attempt::query()
            ->whereBelongsTo($user)
            ->where('status', Attempt::STATUS_FINISHED)
            ->latest('finished_at')
            ->limit(5)
            ->get(['id', 'score', 'started_at', 'finished_at', 'created_at'])
            ->map(fn (Attempt $attempt): array => [
                'id' => $attempt->id,
                'score' => round((float) ($attempt->score ?? 0), 2),
                'created_at' => $attempt->created_at?->toISOString(),
                'duration' => $this->durationInMinutes($attempt),
            ])
            ->all();

        return [
            'total_attempts' => $totalAttempts,
            'average_score' => $averageScore,
            'best_score' => $bestScore,
            'total_time' => $totalTime,
            'category_performance' => $categoryPerformance,
            'incorrect_count' => $incorrectCount,
            'recent_attempts' => $recentAttempts,
        ];
    }

    private function durationInMinutes(Attempt $attempt): int
    {
        if ($attempt->started_at === null || $attempt->finished_at === null) {
            return 0;
        }

        $durationInSeconds = $attempt->started_at->diffInSeconds($attempt->finished_at, false);

        if ($durationInSeconds <= 0) {
            return 0;
        }

        return (int) ceil($durationInSeconds / 60);
    }
}
