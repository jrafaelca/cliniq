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
            ->withSum('answers as total_time_seconds', 'time_spent_seconds')
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
        $durationInSeconds = (int) ($attempt->total_time_seconds ?? 0);

        if ($durationInSeconds <= 0) {
            return 0;
        }

        return (int) ceil($durationInSeconds / 60);
    }
}
