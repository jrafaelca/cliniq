<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PracticeController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::post('practice/start', [PracticeController::class, 'start'])->name('practice.start');
    Route::get('practice/{attempt}', [PracticeController::class, 'show'])->name('practice.show');
    Route::post('practice/{attempt}/answer', [PracticeController::class, 'answer'])->name('practice.answer');
    Route::post('practice/{attempt}/finish', [PracticeController::class, 'finish'])->name('practice.finish');
});

require __DIR__.'/settings.php';
