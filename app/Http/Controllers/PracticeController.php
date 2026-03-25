<?php

namespace App\Http\Controllers;

use App\Http\Requests\Practice\AnswerQuestionRequest;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    /**
     * Start a new practice attempt or resume the active one.
     */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        $activeAttempt = $user->attempts()
            ->where('status', Attempt::STATUS_ACTIVE)
            ->latest('started_at')
            ->first();

        if ($activeAttempt !== null) {
            return to_route('practice.show', $activeAttempt);
        }

        $questionIds = Question::query()
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
            'question_ids' => $questionIds,
            'started_at' => now(),
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

        AttemptAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $questionId,
            'selected_options' => $selectedOptionIds->all(),
            'is_correct' => $isCorrect,
        ]);

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
        $totalQuestions = count($attempt->question_ids ?? []);
        $correctCount = $attempt->answers()->where('is_correct', true)->count();
        $incorrectCount = max(0, $totalQuestions - $correctCount);

        return [
            'attemptId' => $attempt->id,
            'score' => $attempt->score,
            'correct_count' => $correctCount,
            'incorrect_count' => $incorrectCount,
            'total_questions' => $totalQuestions,
            'started_at' => $attempt->started_at,
            'finished_at' => $attempt->finished_at,
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
        ]);
    }
}
