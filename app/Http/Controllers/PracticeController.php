<?php

namespace App\Http\Controllers;

use App\Http\Requests\Practice\AnswerQuestionRequest;
use App\Http\Requests\Practice\StartPracticeAttemptRequest;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    /**
     * Resolve the practice landing action from navigation.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $activeAttempt = $user
            ->attempts()
            ->where('status', Attempt::STATUS_ACTIVE)
            ->latest('started_at')
            ->first();

        $remainingQuestions = null;
        $activeAttemptMode = null;
        $activeAttemptProgressPercent = null;
        $activeAttemptLastActivityAt = null;

        if ($activeAttempt !== null) {
            $answeredCount = $activeAttempt->answers()->count();
            $totalQuestions = count($activeAttempt->question_ids ?? []);
            $remainingQuestions = max(0, $totalQuestions - $answeredCount);
            $activeAttemptMode = $activeAttempt->mode ?? Attempt::MODE_PRACTICE;
            $activeAttemptProgressPercent = $totalQuestions > 0
                ? round(($answeredCount / $totalQuestions) * 100, 2)
                : 0;
            $activeAttemptLastActivityAt = $activeAttempt->last_activity_at?->toIso8601String();
        }

        $questionsQuery = Question::query();

        if ($user->subject_id !== null) {
            $questionsQuery->where('subject_id', $user->subject_id);
        }

        return Inertia::render('Practice/Entry', [
            'activeAttemptId' => $activeAttempt?->id,
            'remainingQuestions' => $remainingQuestions,
            'activeAttemptMode' => $activeAttemptMode,
            'activeAttemptProgressPercent' => $activeAttemptProgressPercent,
            'activeAttemptLastActivityAt' => $activeAttemptLastActivityAt,
            'hasQuestions' => $questionsQuery->exists(),
        ]);
    }

    /**
     * Start a new practice attempt or resume the active one.
     */
    public function start(StartPracticeAttemptRequest $request): RedirectResponse
    {
        $user = $request->user();
        $shouldRestart = $request->boolean('restart');
        $mode = (string) ($request->validated('mode') ?? Attempt::MODE_PRACTICE);

        $activeAttempt = $user->attempts()
            ->where('status', Attempt::STATUS_ACTIVE)
            ->latest('started_at')
            ->first();

        if ($activeAttempt !== null) {
            if ($shouldRestart) {
                if (! $request->has('mode')) {
                    $mode = (string) ($activeAttempt->mode ?? Attempt::MODE_PRACTICE);
                }

                $this->expireAttempt($activeAttempt);
            } else {
                return to_route('practice.show', $activeAttempt);
            }
        }

        $questionsQuery = Question::query();

        if ($user->subject_id !== null) {
            $questionsQuery->where('subject_id', $user->subject_id);
        }

        $questionIds = $questionsQuery
            ->inRandomOrder()
            ->limit(10)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->values()
            ->all();

        if ($questionIds === []) {
            return to_route('dashboard')->with(
                'practice_error',
                'Aún no hay preguntas disponibles. Inténtalo nuevamente en unos minutos.',
            );
        }

        $attempt = Attempt::query()->create([
            'user_id' => $user->id,
            'status' => Attempt::STATUS_ACTIVE,
            'mode' => $mode,
            'time_limit_seconds' => $this->resolveTimeLimitForMode($mode),
            'question_ids' => $questionIds,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        return to_route('practice.show', $attempt);
    }

    /**
     * Show the current practice question or the final result.
     */
    public function show(Request $request, Attempt $attempt): Response|RedirectResponse
    {
        $this->ensureAttemptOwnership($request, $attempt);

        if ($attempt->status === Attempt::STATUS_FINISHED) {
            return Inertia::render('Practice/Result', $this->buildResultProps($attempt));
        }

        if ($attempt->status === Attempt::STATUS_EXPIRED) {
            return to_route('dashboard')->with(
                'practice_error',
                __('practice.session_expired'),
            );
        }

        if ($this->hasExceededSimulationTimeLimit($attempt)) {
            $this->finishAttempt($attempt);

            return Inertia::render('Practice/Result', $this->buildResultProps($attempt->fresh()));
        }

        if ($this->hasExceededInactivityLimit($attempt) && ! $request->boolean('resume')) {
            return Inertia::render('Practice/Paused', [
                'attemptId' => $attempt->id,
                'inactivity_limit_minutes' => Attempt::INACTIVITY_LIMIT_MINUTES,
            ]);
        }

        $this->markAttemptActivity($attempt);

        $questionIds = collect($attempt->question_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $totalQuestions = $questionIds->count();

        if ($totalQuestions === 0) {
            return to_route('dashboard')->with(
                'practice_error',
                'Este intento no tiene preguntas disponibles.',
            );
        }

        $answeredCount = $attempt->answers()->count();

        if ($answeredCount >= $totalQuestions) {
            $this->finishAttempt($attempt);

            return Inertia::render('Practice/Result', $this->buildResultProps($attempt->fresh()));
        }

        $question = Question::query()
            ->with('options')
            ->findOrFail($questionIds->get($answeredCount));

        return Inertia::render('Practice/Question', [
            'attemptId' => $attempt->id,
            'attempt_mode' => $attempt->mode ?? Attempt::MODE_PRACTICE,
            'attempt_time_limit_seconds' => $attempt->time_limit_seconds,
            'attempt_started_at' => $attempt->started_at,
            'settings' => $this->resolvePracticeSettings($request->user()),
            'question' => [
                'id' => $question->id,
                'statement' => $question->statement,
                'type' => $question->type,
                'options' => $question->options
                    ->map(fn ($option): array => [
                        'id' => $option->id,
                        'text' => $option->text,
                    ])
                    ->values(),
            ],
            'progress' => [
                'current' => $answeredCount + 1,
                'total' => $totalQuestions,
                'percent' => round(($answeredCount / $totalQuestions) * 100, 2),
            ],
        ]);
    }

    /**
     * Save an answer and return immediate feedback.
     */
    public function answer(AnswerQuestionRequest $request, Attempt $attempt)
    {
        $this->ensureAttemptOwnership($request, $attempt);

        if ($attempt->status !== Attempt::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'attempt' => 'Este intento ya fue finalizado.',
            ]);
        }

        if ($this->hasExceededSimulationTimeLimit($attempt)) {
            $this->finishAttempt($attempt);

            throw ValidationException::withMessages([
                'attempt' => __('practice.simulation_time_expired'),
            ]);
        }

        if ($this->hasExceededInactivityLimit($attempt)) {
            throw ValidationException::withMessages([
                'attempt' => __('practice.session_expired'),
            ]);
        }

        $questionIds = collect($attempt->question_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $totalQuestions = $questionIds->count();
        $answeredCount = $attempt->answers()->count();

        if ($answeredCount >= $totalQuestions) {
            throw ValidationException::withMessages([
                'attempt' => 'No hay más preguntas pendientes para responder.',
            ]);
        }

        $questionId = (int) $request->integer('question_id');
        $currentQuestionId = (int) $questionIds->get($answeredCount);

        if ($questionId !== $currentQuestionId) {
            throw ValidationException::withMessages([
                'question_id' => 'Debes responder la pregunta actual para continuar.',
            ]);
        }

        if ($attempt->answers()->where('question_id', $questionId)->exists()) {
            throw ValidationException::withMessages([
                'question_id' => 'Esta pregunta ya fue respondida en el intento actual.',
            ]);
        }

        $question = Question::query()
            ->with('options')
            ->findOrFail($questionId);

        $selectedOptionIds = collect($request->input('selected_options', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $validOptionIds = $question->options
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        if ($selectedOptionIds->isEmpty() || $selectedOptionIds->diff($validOptionIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'selected_options' => 'Las opciones enviadas no son válidas para esta pregunta.',
            ]);
        }

        if ($question->type === Question::TYPE_SINGLE && $selectedOptionIds->count() !== 1) {
            throw ValidationException::withMessages([
                'selected_options' => 'Esta pregunta permite seleccionar solo una opción.',
            ]);
        }

        $correctOptionIds = $question->options
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values();

        $isCorrect = $selectedOptionIds->all() === $correctOptionIds->all();

        $timeSpentSeconds = min(300, (int) $request->integer('time_spent_seconds'));
        $timeSpentSeconds = max(1, $timeSpentSeconds);

        AttemptAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $questionId,
            'selected_options' => $selectedOptionIds->all(),
            'is_correct' => $isCorrect,
            'time_spent_seconds' => $timeSpentSeconds,
        ]);

        $this->markAttemptActivity($attempt);

        $answeredCountAfter = $answeredCount + 1;

        return response()->json([
            'is_correct' => $isCorrect,
            'correct_option_ids' => $correctOptionIds->all(),
            'explanation' => $question->explanation,
            'answered_count' => $answeredCountAfter,
            'total_questions' => $totalQuestions,
            'is_last_question' => $answeredCountAfter >= $totalQuestions,
        ]);
    }

    /**
     * Finish an active attempt and compute the score.
     */
    public function finish(Request $request, Attempt $attempt): RedirectResponse
    {
        $this->ensureAttemptOwnership($request, $attempt);

        if ($attempt->status === Attempt::STATUS_FINISHED) {
            return to_route('practice.show', $attempt);
        }

        $totalQuestions = count($attempt->question_ids ?? []);
        $answeredCount = $attempt->answers()->count();

        if ($answeredCount < $totalQuestions) {
            throw ValidationException::withMessages([
                'attempt' => 'Debes responder todas las preguntas antes de finalizar.',
            ]);
        }

        $this->finishAttempt($attempt);

        return to_route('practice.show', $attempt);
    }

    private function ensureAttemptOwnership(Request $request, Attempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResultProps(Attempt $attempt): array
    {
        $questionIds = collect($attempt->question_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $totalQuestions = $questionIds->count();
        $correctCount = $attempt->answers()->where('is_correct', true)->count();
        $incorrectCount = max(0, $totalQuestions - $correctCount);
        $totalTimeSeconds = (int) $attempt->answers()->sum('time_spent_seconds');
        $questionsById = Question::query()
            ->with('options')
            ->whereIn('id', $questionIds->all())
            ->get()
            ->keyBy('id');
        $answersByQuestionId = $attempt->answers()
            ->get()
            ->keyBy('question_id');
        $questionFeedback = $questionIds
            ->map(function (int $questionId) use ($questionsById, $answersByQuestionId): ?array {
                $question = $questionsById->get($questionId);

                if ($question === null) {
                    return null;
                }

                $answer = $answersByQuestionId->get($questionId);
                $selectedOptionIds = collect($answer?->selected_options ?? [])
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values();
                $optionsById = $question->options->keyBy('id');
                $selectedOptionTexts = $selectedOptionIds
                    ->map(fn (int $optionId): ?string => $optionsById->get($optionId)?->text)
                    ->filter(fn (?string $text): bool => is_string($text) && $text !== '')
                    ->values()
                    ->all();
                $correctOptionTexts = $question->options
                    ->where('is_correct', true)
                    ->pluck('text')
                    ->values()
                    ->all();

                return [
                    'question_id' => $question->id,
                    'statement' => $question->statement,
                    'is_answered' => $answer !== null,
                    'is_correct' => $answer?->is_correct,
                    'selected_option_texts' => $selectedOptionTexts,
                    'correct_option_texts' => $correctOptionTexts,
                    'explanation' => $question->explanation,
                    'time_spent_seconds' => (int) ($answer?->time_spent_seconds ?? 0),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'attemptId' => $attempt->id,
            'score' => $attempt->score,
            'correct_count' => $correctCount,
            'incorrect_count' => $incorrectCount,
            'total_questions' => $totalQuestions,
            'question_feedback' => $questionFeedback,
            // Keep compatibility with already-cached frontend bundles.
            'started_at' => $attempt->started_at,
            'finished_at' => $attempt->finished_at,
            'total_time_seconds' => $totalTimeSeconds,
            'average_time_per_question_seconds' => $totalQuestions > 0
                ? (int) ceil($totalTimeSeconds / $totalQuestions)
                : 0,
        ];
    }

    private function finishAttempt(Attempt $attempt): void
    {
        $totalQuestions = count($attempt->question_ids ?? []);
        $correctCount = $attempt->answers()->where('is_correct', true)->count();

        $score = $totalQuestions > 0
            ? round(($correctCount / $totalQuestions) * 100, 2)
            : 0;

        $attempt->update([
            'status' => Attempt::STATUS_FINISHED,
            'score' => $score,
            'finished_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    private function hasExceededInactivityLimit(Attempt $attempt): bool
    {
        if ($attempt->last_activity_at === null) {
            return false;
        }

        return $attempt->last_activity_at
            ->lt(now()->subMinutes(Attempt::INACTIVITY_LIMIT_MINUTES));
    }

    private function hasExceededSimulationTimeLimit(Attempt $attempt): bool
    {
        if ($attempt->mode !== Attempt::MODE_SIMULATION) {
            return false;
        }

        if ($attempt->time_limit_seconds === null || $attempt->started_at === null) {
            return false;
        }

        return $attempt->started_at
            ->addSeconds((int) $attempt->time_limit_seconds)
            ->lte(now());
    }

    private function markAttemptActivity(Attempt $attempt): void
    {
        $attempt->update([
            'last_activity_at' => now(),
        ]);
    }

    private function expireAttempt(Attempt $attempt): void
    {
        $attempt->update([
            'status' => Attempt::STATUS_EXPIRED,
            'finished_at' => now(),
        ]);
    }

    private function resolveTimeLimitForMode(string $mode): ?int
    {
        if ($mode === Attempt::MODE_SIMULATION) {
            return Attempt::SIMULATION_TIME_LIMIT_SECONDS;
        }

        return null;
    }

    /**
     * @return array{auto_advance: bool, auto_advance_delay: int}
     */
    private function resolvePracticeSettings(User $user): array
    {
        $settings = $user->settings()->firstOrCreate([], [
            'preferences' => UserSettings::defaultPreferences(),
        ]);

        return UserSettings::normalizePreferences(
            is_array($settings->preferences) ? $settings->preferences : null,
        );
    }
}
