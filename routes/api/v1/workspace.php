<?php

use App\Http\Controllers\Api\V1\Assessment\StudentAssessmentController;
use App\Http\Controllers\Api\V1\Workspace\WorkspaceChatController;
use App\Http\Controllers\Api\V1\Workspace\WorkspaceController;
use App\Http\Controllers\Api\V1\Workspace\WorkspaceFileController;
use App\Http\Controllers\Api\V1\Workspace\WorkspaceTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('assessments')->name('assessments.')->group(function () {
    Route::get('/', [StudentAssessmentController::class, 'index'])->name('index');
    Route::get('/submission-files/{file}/download', [StudentAssessmentController::class, 'downloadFile'])->name('files.download');
    Route::get('/courses/{course}/{assessment}', [StudentAssessmentController::class, 'show'])->name('show');
    Route::get('/courses/{course}/{assessment}/instruction', [StudentAssessmentController::class, 'downloadInstruction'])->name('instruction');
    Route::post('/courses/{course}/{assessment}/submit', [StudentAssessmentController::class, 'submit'])->name('submit');
    Route::post('/courses/{course}/{assessment}/resubmit', [StudentAssessmentController::class, 'resubmit'])->name('resubmit');
    Route::delete('/courses/{course}/{assessment}/submission', [StudentAssessmentController::class, 'destroySubmission'])->name('submission.destroy');
});

Route::prefix('workspace')->name('workspace.')->group(function () {
    Route::get('/', [WorkspaceController::class, 'index'])->name('index');
    Route::get('/{group}', [WorkspaceController::class, 'show'])->name('show');

    Route::get('/{group}/chat', [WorkspaceChatController::class, 'index'])->name('chat.index');
    Route::post('/{group}/chat', [WorkspaceChatController::class, 'store'])->name('chat.store');
    Route::patch('/{group}/chat/{message}', [WorkspaceChatController::class, 'update'])->name('chat.update');
    Route::delete('/{group}/chat/{message}', [WorkspaceChatController::class, 'destroy'])->name('chat.destroy');

    Route::get('/{group}/tasks', [WorkspaceTaskController::class, 'index'])->name('tasks.index');
    Route::post('/{group}/tasks', [WorkspaceTaskController::class, 'store'])->name('tasks.store');
    Route::patch('/{group}/tasks/{task}', [WorkspaceTaskController::class, 'update'])->name('tasks.update');
    Route::delete('/{group}/tasks/{task}', [WorkspaceTaskController::class, 'destroy'])->name('tasks.destroy');

    Route::get('/{group}/files', [WorkspaceFileController::class, 'index'])->name('files.index');
    Route::post('/{group}/files', [WorkspaceFileController::class, 'store'])->name('files.store');
    Route::get('/{group}/files/{file}/download', [WorkspaceFileController::class, 'download'])->name('files.download');
    Route::delete('/{group}/files/{file}', [WorkspaceFileController::class, 'destroy'])->name('files.destroy');
    Route::post('/{group}/folders', [WorkspaceFileController::class, 'storeFolder'])->name('folders.store');
    Route::delete('/{group}/folders/{folder}', [WorkspaceFileController::class, 'destroyFolder'])->name('folders.destroy');
});
