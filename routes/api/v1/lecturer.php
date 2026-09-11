<?php

use App\Http\Controllers\Api\V1\Lecturer\AttendanceController;
use App\Http\Controllers\Api\V1\Lecturer\CourseController;
use App\Http\Controllers\Api\V1\Lecturer\DashboardController;
use App\Http\Controllers\Api\V1\Lecturer\SectionController;
use App\Http\Controllers\Api\V1\Lecturer\WheelController;
use Illuminate\Support\Facades\Route;

Route::prefix('lecturer')->name('lecturer.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    // Courses & sections
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->whereNumber('course')->name('courses.show');
    Route::get('/courses/{course}/sections/{section}', [SectionController::class, 'show'])->name('sections.show');
    Route::post('/courses/{course}/sections/{section}/toggle-active', [SectionController::class, 'toggleActive'])->name('sections.toggle-active');
    Route::post('/courses/{course}/sections/{section}/students', [SectionController::class, 'addStudent'])->name('sections.students.add');
    Route::delete('/courses/{course}/sections/{section}/students/{user}', [SectionController::class, 'removeStudent'])->name('sections.students.remove');

    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/start', [AttendanceController::class, 'start'])->name('attendance.start');
    Route::get('/attendance/{session}', [AttendanceController::class, 'show'])->whereNumber('session')->name('attendance.show');
    Route::put('/attendance/{session}', [AttendanceController::class, 'update'])->whereNumber('session')->name('attendance.update');
    Route::delete('/attendance/{session}', [AttendanceController::class, 'destroy'])->whereNumber('session')->name('attendance.destroy');
    Route::get('/attendance/{session}/token', [AttendanceController::class, 'token'])->name('attendance.token');
    Route::post('/attendance/{session}/end', [AttendanceController::class, 'end'])->name('attendance.end');
    Route::post('/attendance/{session}/reopen', [AttendanceController::class, 'reopen'])->name('attendance.reopen');
    Route::put('/attendance/{session}/records/{record}', [AttendanceController::class, 'override'])->name('attendance.override');

    // Random present-student wheel
    Route::get('/wheel', [WheelController::class, 'index'])->name('wheel.index');
    Route::get('/wheel/sessions', [WheelController::class, 'sessions'])->name('wheel.sessions');
    Route::get('/wheel/present-students', [WheelController::class, 'presentStudents'])->name('wheel.present-students');
});
