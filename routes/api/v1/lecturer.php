<?php

use App\Http\Controllers\Api\V1\Lecturer\AssessmentMarkingController;
use App\Http\Controllers\Api\V1\Lecturer\AssignmentMarkingController;
use App\Http\Controllers\Api\V1\Lecturer\AttendanceController;
use App\Http\Controllers\Api\V1\Lecturer\AttendancePolicyController;
use App\Http\Controllers\Api\V1\Lecturer\CourseController;
use App\Http\Controllers\Api\V1\Lecturer\CourseManagementController;
use App\Http\Controllers\Api\V1\Lecturer\DashboardController;
use App\Http\Controllers\Api\V1\Lecturer\ExcuseController;
use App\Http\Controllers\Api\V1\Lecturer\GroupController;
use App\Http\Controllers\Api\V1\Lecturer\MaterialController;
use App\Http\Controllers\Api\V1\Lecturer\SectionController;
use App\Http\Controllers\Api\V1\Lecturer\WatchController;
use App\Http\Controllers\Api\V1\Lecturer\WheelController;
use Illuminate\Support\Facades\Route;

Route::prefix('lecturer')->name('lecturer.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    // Courses & sections
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::post('/courses/join', [CourseController::class, 'join'])->name('courses.join');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->whereNumber('course')->name('courses.show');
    Route::get('/courses/{course}/attendance-policy', [AttendancePolicyController::class, 'show'])->name('courses.attendance-policy.show');
    Route::put('/courses/{course}/attendance-policy', [AttendancePolicyController::class, 'update'])->name('courses.attendance-policy.update');
    Route::get('/course-options', [CourseManagementController::class, 'options'])->name('courses.options');
    Route::post('/courses', [CourseManagementController::class, 'store'])->name('courses.store');
    Route::put('/courses/{course}', [CourseManagementController::class, 'update'])->whereNumber('course')->name('courses.update');
    Route::delete('/courses/{course}', [CourseManagementController::class, 'destroy'])->whereNumber('course')->name('courses.destroy');
    Route::post('/courses/{course}/clos', [CourseManagementController::class, 'storeClo'])->name('courses.clos.store');
    Route::delete('/courses/{course}/clos/{clo}', [CourseManagementController::class, 'destroyClo'])->name('courses.clos.destroy');
    Route::post('/courses/{course}/topics', [CourseManagementController::class, 'storeTopic'])->name('courses.topics.store');
    Route::delete('/courses/{course}/topics/{topic}', [CourseManagementController::class, 'destroyTopic'])->name('courses.topics.destroy');
    Route::post('/courses/{course}/sections', [SectionController::class, 'store'])->name('sections.store');
    Route::get('/courses/{course}/sections/{section}', [SectionController::class, 'show'])->name('sections.show');
    Route::put('/courses/{course}/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
    Route::put('/courses/{course}/sections/{section}/schedule', [SectionController::class, 'updateSchedule'])->name('sections.schedule');
    Route::post('/courses/{course}/sections/{section}/students/import', [SectionController::class, 'importCsv'])->name('sections.students.import');
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

    // Course materials
    Route::get('/courses/{course}/materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::post('/courses/{course}/materials/sections', [MaterialController::class, 'storeSection'])->name('materials.sections.store');
    Route::patch('/courses/{course}/materials/sections/{section}', [MaterialController::class, 'updateSection'])->name('materials.sections.update');
    Route::delete('/courses/{course}/materials/sections/{section}', [MaterialController::class, 'destroySection'])->name('materials.sections.destroy');
    Route::post('/courses/{course}/materials/sections/{section}/move', [MaterialController::class, 'moveSection'])->name('materials.sections.move');
    Route::post('/courses/{course}/materials/sections/{section}/files', [MaterialController::class, 'upload'])->name('materials.upload');
    Route::post('/courses/{course}/materials/sections/{section}/links', [MaterialController::class, 'storeLink'])->name('materials.links.store');
    Route::patch('/courses/{course}/materials/items/{file}', [MaterialController::class, 'updateItem'])->name('materials.items.update');
    Route::delete('/courses/{course}/materials/items/{file}', [MaterialController::class, 'destroyItem'])->name('materials.items.destroy');

    // Watch analytics (episodes are uploaded on the web)
    Route::get('/courses/{course}/watch', [WatchController::class, 'course'])->name('watch.course');
    Route::get('/courses/{course}/watch/preview', [WatchController::class, 'preview'])->name('watch.preview');
    Route::get('/watch/episodes/{episode}', [WatchController::class, 'show'])->name('watch.episodes.show');
    Route::get('/watch/episodes/{episode}/preview', [WatchController::class, 'previewEpisode'])->name('watch.episodes.preview');
    Route::patch('/watch/episodes/{episode}/release', [WatchController::class, 'release'])->name('watch.episodes.release');
    Route::post('/watch/episodes/{episode}/remind', [WatchController::class, 'remind'])->name('watch.episodes.remind');

    // Student groups
    Route::get('/courses/{course}/group-sets', [GroupController::class, 'index'])->name('group-sets.index');
    Route::post('/courses/{course}/group-sets', [GroupController::class, 'store'])->name('group-sets.store');
    Route::get('/courses/{course}/group-sets/{set}', [GroupController::class, 'show'])->name('group-sets.show');
    Route::patch('/courses/{course}/group-sets/{set}', [GroupController::class, 'update'])->name('group-sets.update');
    Route::delete('/courses/{course}/group-sets/{set}', [GroupController::class, 'destroy'])->name('group-sets.destroy');
    Route::post('/courses/{course}/group-sets/{set}/arrange-random', [GroupController::class, 'arrangeRandom'])->name('group-sets.arrange');
    Route::post('/courses/{course}/group-sets/{set}/groups', [GroupController::class, 'storeGroup'])->name('group-sets.groups.store');
    Route::patch('/courses/{course}/group-sets/{set}/groups/{group}', [GroupController::class, 'updateGroup'])->name('group-sets.groups.update');
    Route::delete('/courses/{course}/group-sets/{set}/groups/{group}', [GroupController::class, 'destroyGroup'])->name('group-sets.groups.destroy');
    Route::post('/courses/{course}/group-sets/{set}/groups/{group}/members', [GroupController::class, 'addMember'])->name('group-sets.members.add');
    Route::delete('/courses/{course}/group-sets/{set}/groups/{group}/members/{user}', [GroupController::class, 'removeMember'])->name('group-sets.members.remove');
    Route::post('/courses/{course}/group-sets/{set}/groups/{group}/leader', [GroupController::class, 'setLeader'])->name('group-sets.leader');
    Route::post('/courses/{course}/group-sets/{set}/members/{user}/move', [GroupController::class, 'moveMember'])->name('group-sets.members.move');

    // Assignment marking
    Route::get('/assignments', [AssignmentMarkingController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/{assignment}', [AssignmentMarkingController::class, 'show'])->name('assignments.show');
    Route::get('/assignments/{assignment}/submissions/{submission}', [AssignmentMarkingController::class, 'submission'])->name('assignments.submissions.show');
    Route::get('/assignments/{assignment}/submissions/{submission}/files/{file}', [AssignmentMarkingController::class, 'file'])->name('assignments.submissions.file');
    Route::post('/assignments/{assignment}/submissions/{submission}/mark', [AssignmentMarkingController::class, 'mark'])->name('assignments.submissions.mark');

    // Assessment marking
    Route::get('/courses/{course}/assessments', [AssessmentMarkingController::class, 'index'])->name('courses.assessments.index');
    Route::get('/assessments/{assessment}', [AssessmentMarkingController::class, 'show'])->name('assessments.show');
    Route::get('/assessments/{assessment}/submissions/{submission}', [AssessmentMarkingController::class, 'submission'])->name('assessments.submissions.show');
    Route::get('/assessments/{assessment}/submissions/{submission}/files/{file}', [AssessmentMarkingController::class, 'file'])->name('assessments.submissions.file');
    Route::put('/assessments/{assessment}/scores/{user}', [AssessmentMarkingController::class, 'mark'])->name('assessments.scores.mark');
    Route::post('/assessments/{assessment}/release', [AssessmentMarkingController::class, 'release'])->name('assessments.release');
    Route::post('/assessments/{assessment}/scores/{score}/unrelease', [AssessmentMarkingController::class, 'unrelease'])->name('assessments.scores.unrelease');

    // Absence excuses
    Route::get('/excuses', [ExcuseController::class, 'index'])->name('excuses.index');
    Route::post('/excuses/{excuse}/approve', [ExcuseController::class, 'approve'])->name('excuses.approve');
    Route::post('/excuses/{excuse}/reject', [ExcuseController::class, 'reject'])->name('excuses.reject');
    Route::get('/excuses/{excuse}/attachment', [ExcuseController::class, 'attachment'])->name('excuses.attachment');

    // Random present-student wheel
    Route::get('/wheel', [WheelController::class, 'index'])->name('wheel.index');
    Route::get('/wheel/sessions', [WheelController::class, 'sessions'])->name('wheel.sessions');
    Route::get('/wheel/present-students', [WheelController::class, 'presentStudents'])->name('wheel.present-students');
    Route::post('/wheel/spins', [WheelController::class, 'storeSpin'])->name('wheel.spins.store');
});
