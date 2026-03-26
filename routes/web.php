<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardStatsController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('dashboard/stats', DashboardStatsController::class)->name('dashboard.stats');

    Route::get('practice', [PracticeController::class, 'index'])->name('practice.index');

    Route::post('practice/start', [PracticeController::class, 'start'])->name('practice.start');
    Route::get('practice/{attempt}', [PracticeController::class, 'show'])->name('practice.show');
    Route::post('practice/{attempt}/answer', [PracticeController::class, 'answer'])->name('practice.answer');
    Route::post('practice/{attempt}/finish', [PracticeController::class, 'finish'])->name('practice.finish');

    Route::get('results', [ResultsController::class, 'index'])->name('results.index');

    Route::post('review/start', [ReviewController::class, 'start'])->name('review.start');
});

require __DIR__.'/settings.php';
