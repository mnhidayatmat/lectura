<?php

use App\Http\Controllers\Api\V1\Live\ActiveSessionController;
use App\Http\Controllers\Api\V1\Live\LiveHubController;
use App\Http\Controllers\Api\V1\Live\QuizPlayController;
use Illuminate\Support\Facades\Route;

Route::prefix('live')->name('live.')->group(function () {
    Route::get('/hub', [LiveHubController::class, 'hub'])->name('hub');
    Route::post('/join', [LiveHubController::class, 'join'])->name('join');

    Route::get('/quizzes/{session}', [QuizPlayController::class, 'show'])->name('quizzes.show');
    Route::get('/quizzes/{session}/state', [QuizPlayController::class, 'state'])->name('quizzes.state');
    Route::post('/quizzes/{session}/respond', [QuizPlayController::class, 'respond'])->name('quizzes.respond');
    Route::post('/quizzes/{session}/submit-offline', [QuizPlayController::class, 'submitOffline'])->name('quizzes.submit-offline');
    Route::get('/quizzes/{session}/result', [QuizPlayController::class, 'result'])->name('quizzes.result');

    Route::get('/sessions/{session}', [ActiveSessionController::class, 'show'])->name('sessions.show');
    Route::get('/sessions/{session}/state', [ActiveSessionController::class, 'state'])->name('sessions.state');
    Route::post('/sessions/{session}/respond', [ActiveSessionController::class, 'respond'])->name('sessions.respond');
    Route::get('/sessions/{session}/review', [ActiveSessionController::class, 'review'])->name('sessions.review');
});
