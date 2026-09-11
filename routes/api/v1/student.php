<?php

use App\Http\Controllers\Api\V1\Student\AssignmentController;
use App\Http\Controllers\Api\V1\Student\AttendanceController;
use App\Http\Controllers\Api\V1\Student\CourseController;
use App\Http\Controllers\Api\V1\Student\DashboardController;
use App\Http\Controllers\Api\V1\Student\MarkController;
use App\Http\Controllers\Api\V1\Student\MaterialController;
use Illuminate\Support\Facades\Route;

Route::prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::post('/courses/enroll', [CourseController::class, 'enroll'])->name('courses.enroll');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/courses/{course}', [AttendanceController::class, 'course'])->name('attendance.course');
    Route::post('/attendance/records/{record}/excuse', [AttendanceController::class, 'submitExcuse'])->name('attendance.excuse');

    Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::get('/materials/courses/{course}', [MaterialController::class, 'course'])->name('materials.course');
    Route::get('/materials/courses/{course}/files/{file}/download', [MaterialController::class, 'download'])->name('materials.download');

    Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->name('assignments.submit');
    Route::get('/assignments/{assignment}/instruction', [AssignmentController::class, 'downloadInstruction'])->name('assignments.instruction');
    Route::get('/assignments/{assignment}/files/{file}/download', [AssignmentController::class, 'downloadFile'])->name('assignments.files.download');
    Route::get('/assignments/{assignment}/files/{file}/annotated', [AssignmentController::class, 'downloadAnnotated'])->name('assignments.files.annotated');

    Route::get('/marks', [MarkController::class, 'index'])->name('marks.index');
    Route::get('/marks/assessment-scores/{score}/answer-script', [MarkController::class, 'downloadAnswerScript'])->name('marks.answer-script');
    Route::get('/marks/{mark}', [MarkController::class, 'show'])->name('marks.show');
});
