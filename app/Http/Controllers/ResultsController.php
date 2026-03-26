<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultsController extends Controller
{
    /**
     * Show paginated finished attempts.
     */
    public function index(Request $request): Response
    {
        $attemptsPaginator = $request->user()
            ->attempts()
            ->where('status', Attempt::STATUS_FINISHED)
            ->latest('finished_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Results/Index', [
            'results' => [
                'data' => collect($attemptsPaginator->items())
                    ->map(fn (Attempt $attempt): array => [
                        'id' => $attempt->id,
                        'score' => round((float) ($attempt->score ?? 0), 2),
                        'finished_at' => $attempt->finished_at?->toISOString(),
                        'duration' => $this->durationInMinutes($attempt),
                    ])
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $attemptsPaginator->currentPage(),
                    'last_page' => $attemptsPaginator->lastPage(),
                    'from' => $attemptsPaginator->firstItem(),
                    'to' => $attemptsPaginator->lastItem(),
                    'total' => $attemptsPaginator->total(),
                ],
                'links' => [
                    'next' => $attemptsPaginator->nextPageUrl(),
                    'prev' => $attemptsPaginator->previousPageUrl(),
                ],
            ],
        ]);
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
