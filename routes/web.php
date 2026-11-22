<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\ParticipantController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Organizer Google sign-in (Filament admin). Allowlisted emails only.
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectAdmin'])
    ->middleware('throttle:20,1')
    ->name('auth.google.redirect');
Route::get('/auth/google/participant', [GoogleAuthController::class, 'redirectParticipant'])
    ->middleware('throttle:20,1')
    ->name('auth.google.participant');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('auth.google.callback');

Route::get('/login', function () {
    if (Auth::guard('participant')->check()) {
        return redirect()->route('participant.home');
    }

    return view('participant.login');
})->name('participant.login');

Route::middleware('auth:participant')->group(function () {
    Route::get('/', [ParticipantController::class, 'home'])->name('participant.home');
    Route::get('/stage/{stage}', [ParticipantController::class, 'stage'])->name('participant.stage');
    Route::post('/done', [ParticipantController::class, 'done'])->name('participant.done');
    Route::get('/finished', [ParticipantController::class, 'finished'])->name('participant.finished');

    Route::prefix('api')->group(function () {
        Route::get('/ping', [ParticipantController::class, 'ping'])->name('participant.ping');
        Route::post('/prompts/{prompt}/select', [ParticipantController::class, 'select'])->name('participant.select');
        Route::post('/prompts/{prompt}/deselect', [ParticipantController::class, 'deselect'])->name('participant.deselect');
        // Hot path: no route-model binding (saves a query), generous rate limit.
        Route::put('/prompts/{prompt}/response', [ParticipantController::class, 'save'])
            ->whereNumber('prompt')
            ->middleware('throttle:120,1')
            ->name('participant.save');
    });
});

// Organizer-only extras (same login as the Filament admin panel).
Route::middleware('auth:web')->group(function () {
    Route::get('/preview/stage/{stage}', [ParticipantController::class, 'preview'])->name('admin.preview');
    Route::get('/view-as/{participant}', [ParticipantController::class, 'viewAs'])->name('admin.view-as');
    Route::get('/view-as/{participant}/stage/{stage}', [ParticipantController::class, 'viewAsStage'])->name('admin.view-as.stage');
    Route::get('/admin-export/responses.csv', ExportController::class)->name('admin.export');
});
