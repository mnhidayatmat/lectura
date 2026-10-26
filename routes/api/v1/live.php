<?php

use App\Http\Controllers\Api\V1\Live\ActiveSessionController;
use App\Http\Controllers\Api\V1\Live\LiveHubController;
use App\Http\Controllers\Api\V1\Live\QuizPlayController;
use App\Http\Controllers\Api\V1\Live\TenderController;
use Illuminate\Support\Facades\Route;

Route::prefix('live')->name('live.')->group(function () {
    Route::get('/hub', [LiveHubController::class, 'hub'])->name('hub');
    Route::post('/join', [LiveHubController::class, 'join'])->name('join');

    Route::get('/quizzes/{session}', [QuizPlayController::class, 'show'])->name('quizzes.show');
    Route::get('/quizzes/{session}/state', [QuizPlayController::class, 'state'])->name('quizzes.state');
    Route::post('/quizzes/{session}/nickname', [QuizPlayController::class, 'nickname'])->name('quizzes.nickname');
    Route::post('/quizzes/{session}/respond', [QuizPlayController::class, 'respond'])->name('quizzes.respond');
    Route::post('/quizzes/{session}/submit-offline', [QuizPlayController::class, 'submitOffline'])->name('quizzes.submit-offline');
    Route::get('/quizzes/{session}/result', [QuizPlayController::class, 'result'])->name('quizzes.result');

    Route::get('/sessions/{session}', [ActiveSessionController::class, 'show'])->name('sessions.show');
    Route::get('/sessions/{session}/state', [ActiveSessionController::class, 'state'])->name('sessions.state');
    Route::post('/sessions/{session}/respond', [ActiveSessionController::class, 'respond'])->name('sessions.respond');
    Route::get('/sessions/{session}/review', [ActiveSessionController::class, 'review'])->name('sessions.review');

    // Piping Design Tender game
    Route::prefix('tender')->name('tender.')->group(function () {
        Route::get('/', [TenderController::class, 'index'])->name('index');
        Route::get('/games/{game}', [TenderController::class, 'game'])->name('games.show');
        Route::get('/sessions/{session}', [TenderController::class, 'session'])->name('sessions.show');
        Route::patch('/sessions/{session}', [TenderController::class, 'setup'])->name('sessions.setup');
        Route::post('/sessions/{session}/judges', [TenderController::class, 'assignJudge'])->name('sessions.judges');
        Route::post('/sessions/{session}/judges/{judge}/paper', [TenderController::class, 'paperCard'])->name('sessions.paper');
        Route::post('/sessions/{session}/start', [TenderController::class, 'start'])->name('sessions.start');
        Route::post('/sessions/{session}/reconcile', [TenderController::class, 'startReconcile'])->name('sessions.reconcile');
        Route::post('/sessions/{session}/reconcile/end', [TenderController::class, 'endReconcile'])->name('sessions.reconcile.end');
        Route::post('/sessions/{session}/announce', [TenderController::class, 'announce'])->name('sessions.announce');
        Route::post('/sessions/{session}/hires', [TenderController::class, 'hire'])->name('sessions.hires');
        Route::post('/sessions/{session}/declare', [TenderController::class, 'declare'])->name('sessions.declare');
        Route::put('/sessions/{session}/card', [TenderController::class, 'saveCard'])->name('sessions.card');
        Route::post('/sessions/{session}/card/submit', [TenderController::class, 'submitCard'])->name('sessions.card.submit');
        Route::post('/sessions/{session}/card/reconciled', [TenderController::class, 'reconciled'])->name('sessions.card.reconciled');
        Route::post('/sessions/{session}/retrench', [TenderController::class, 'retrench'])->name('sessions.retrench');
        Route::post('/sessions/{session}/appeal', [TenderController::class, 'appeal'])->name('sessions.appeal');
    });
});
