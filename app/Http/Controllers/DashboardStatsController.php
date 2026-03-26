<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\DashboardStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardStatsController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $dashboardStatsService,
    ) {}

    /**
     * Return dashboard stats payload for the authenticated user.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboardStatsService->forUser($request->user()),
        );
    }
}
