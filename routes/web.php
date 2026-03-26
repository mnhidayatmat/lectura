<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\AnalyticsController;
use App\Http\Controllers\Tenant\AssignmentController;
use App\Http\Controllers\Tenant\AttendanceController;
use App\Http\Controllers\Tenant\QuizController;
use App\Http\Controllers\Tenant\CloController;
use App\Http\Controllers\Tenant\CourseController;
use App\Http\Controllers\Tenant\CourseFileController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\SectionController;
use App\Http\Controllers\Tenant\TeachingPlanController;
use App\Http\Controllers\Tenant\TopicController;
use Illuminate\Support\Facades\Route;

// ── Public ──
Route::get('/', function () {
    return view('welcome');
});

// ── Auth (Breeze) ──
Route::get('/dashboard', function () {
    $user = auth()->user();

    // If user has tenants, redirect to first active tenant dashboard
    $tenant = $user->activeTenants()->first();
    if ($tenant) {
        return redirect("/{$tenant->slug}/dashboard");
    }

    // Super admin without tenant goes to admin panel
    if ($user->is_super_admin) {
        return redirect('/admin');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ── Super Admin ──
Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::get('/', function () {
        if (! auth()->user()->is_super_admin) { abort(403); }
        $tenants = \App\Models\Tenant::withCount('tenantUsers')->get();
        $totalUsers = \App\Models\User::count();
        return view('admin.dashboard', compact('tenants', 'totalUsers'));
    })->name('admin.dashboard');

    Route::get('/tenants', function () {
        if (! auth()->user()->is_super_admin) { abort(403); }
        $tenants = \App\Models\Tenant::withCount('tenantUsers')->latest()->get();
        return view('admin.tenants', compact('tenants'));
    })->name('admin.tenants');

    Route::get('/users', function () {
        if (! auth()->user()->is_super_admin) { abort(403); }
        $users = \App\Models\User::with('tenantUsers.tenant')->latest()->get();
        return view('admin.users', compact('users'));
    })->name('admin.users');

    Route::get('/ai-usage', function () {
        if (! auth()->user()->is_super_admin) { abort(403); }
        return view('admin.placeholder', ['title' => 'AI Usage', 'description' => 'Monitor AI credit usage across all tenants.']);
    })->name('admin.ai-usage');

    Route::get('/activity', function () {
        if (! auth()->user()->is_super_admin) { abort(403); }
        return view('admin.placeholder', ['title' => 'Activity Log', 'description' => 'System-wide audit trail of important actions.']);
    })->name('admin.activity');
});

// ── Tenant-Scoped Routes ──
Route::prefix('{tenant:slug}')
    ->middleware(['auth', 'tenant', 'tenant.access', 'locale'])
    ->group(function () {
        Route::get('/dashboard', function () {
            $tenant = app('current_tenant');
            $user = auth()->user();
            $role = $user->roleInTenant($tenant->id);

            $courseCount = 0;
            $studentCount = 0;
            $courses = collect();

            if ($role !== 'student') {
                $courses = \App\Models\Course::where('lecturer_id', $user->id)
                    ->withCount('sections')
                    ->latest()->get();
                $courseCount = $courses->count();
                $sectionIds = \App\Models\Section::whereIn('course_id', $courses->pluck('id'))->pluck('id');
                $studentCount = \App\Models\SectionStudent::whereIn('section_id', $sectionIds)->where('is_active', true)->distinct('user_id')->count('user_id');
            }

            return view('tenant.dashboard', compact('tenant', 'role', 'courseCount', 'studentCount', 'courses'));
        })->name('tenant.dashboard');

        // Course Management
        Route::get('/courses', [CourseController::class, 'index'])->name('tenant.courses.index');
        Route::get('/courses/create', [CourseController::class, 'create'])->name('tenant.courses.create');
        Route::post('/courses', [CourseController::class, 'store'])->name('tenant.courses.store');
        Route::get('/courses/{course}', [CourseController::class, 'show'])->name('tenant.courses.show');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('tenant.courses.edit');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('tenant.courses.update');
        Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('tenant.courses.destroy');

        // Teaching Plans
        Route::get('/courses/{course}/teaching-plan', [TeachingPlanController::class, 'show'])->name('tenant.teaching-plan.show');
        Route::post('/courses/{course}/teaching-plan/generate', [TeachingPlanController::class, 'generate'])->name('tenant.teaching-plan.generate');
        Route::put('/courses/{course}/teaching-plan/weeks/{week}', [TeachingPlanController::class, 'updateWeek'])->name('tenant.teaching-plan.update-week');
        Route::post('/courses/{course}/teaching-plan/{plan}/publish', [TeachingPlanController::class, 'publish'])->name('tenant.teaching-plan.publish');
        Route::get('/courses/{course}/teaching-plan/{plan}', [TeachingPlanController::class, 'version'])->name('tenant.teaching-plan.version');

        // CLOs
        Route::post('/courses/{course}/clos', [CloController::class, 'store'])->name('tenant.courses.clos.store');
        Route::delete('/courses/{course}/clos/{clo}', [CloController::class, 'destroy'])->name('tenant.courses.clos.destroy');

        // Topics
        Route::post('/courses/{course}/topics', [TopicController::class, 'store'])->name('tenant.courses.topics.store');
        Route::delete('/courses/{course}/topics/{topic}', [TopicController::class, 'destroy'])->name('tenant.courses.topics.destroy');

        // Sections
        Route::post('/courses/{course}/sections', [SectionController::class, 'store'])->name('tenant.courses.sections.store');
        Route::get('/courses/{course}/sections/{section}', [SectionController::class, 'show'])->name('tenant.courses.sections.show');
        Route::post('/courses/{course}/sections/{section}/students', [SectionController::class, 'addStudent'])->name('tenant.courses.sections.students.add');
        Route::post('/courses/{course}/sections/{section}/students/import', [SectionController::class, 'importCsv'])->name('tenant.courses.sections.students.import');
        Route::delete('/courses/{course}/sections/{section}/students/{user}', [SectionController::class, 'removeStudent'])->name('tenant.courses.sections.students.remove');

        // Attendance
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('tenant.attendance.index');
        Route::post('/attendance/start', [AttendanceController::class, 'start'])->name('tenant.attendance.start');
        Route::get('/attendance/{session}/qr', [AttendanceController::class, 'qr'])->name('tenant.attendance.qr');
        Route::get('/attendance/{session}/token', [AttendanceController::class, 'refreshToken'])->name('tenant.attendance.token');
        Route::post('/attendance/{session}/end', [AttendanceController::class, 'end'])->name('tenant.attendance.end');
        Route::get('/attendance/{session}', [AttendanceController::class, 'show'])->name('tenant.attendance.show');
        Route::put('/attendance/{session}/records/{record}', [AttendanceController::class, 'override'])->name('tenant.attendance.override');

        // Student check-in API
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('tenant.attendance.checkin');

        // Live Quizzes
        Route::get('/quizzes', [QuizController::class, 'index'])->name('tenant.quizzes.index');
        Route::get('/quizzes/create', [QuizController::class, 'create'])->name('tenant.quizzes.create');
        Route::post('/quizzes', [QuizController::class, 'store'])->name('tenant.quizzes.store');
        Route::get('/quizzes/{session}/control', [QuizController::class, 'control'])->name('tenant.quizzes.control');
        Route::post('/quizzes/{session}/start', [QuizController::class, 'start'])->name('tenant.quizzes.start');
        Route::post('/quizzes/{session}/next', [QuizController::class, 'nextQuestion'])->name('tenant.quizzes.next');
        Route::post('/quizzes/{session}/end', [QuizController::class, 'end'])->name('tenant.quizzes.end');
        Route::get('/quizzes/{session}/results', [QuizController::class, 'results'])->name('tenant.quizzes.results');
        Route::get('/quizzes/{session}/state', [QuizController::class, 'state'])->name('tenant.quizzes.state');
        // Student quiz
        Route::get('/quiz/join', [QuizController::class, 'join'])->name('tenant.quizzes.join');
        Route::get('/quiz/{session}/play', [QuizController::class, 'play'])->name('tenant.quizzes.play');
        Route::post('/quiz/{session}/respond', [QuizController::class, 'respond'])->name('tenant.quizzes.respond');
        Route::get('/quiz/{session}/student-state', [QuizController::class, 'studentState'])->name('tenant.quizzes.student-state');

        // Assignments
        Route::get('/assignments', [AssignmentController::class, 'index'])->name('tenant.assignments.index');
        Route::get('/assignments/create', [AssignmentController::class, 'create'])->name('tenant.assignments.create');
        Route::post('/assignments', [AssignmentController::class, 'store'])->name('tenant.assignments.store');
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('tenant.assignments.show');
        Route::post('/assignments/{assignment}/publish', [AssignmentController::class, 'publish'])->name('tenant.assignments.publish');
        Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->name('tenant.assignments.submit');
        Route::get('/assignments/{assignment}/submissions/{submission}', [AssignmentController::class, 'review'])->name('tenant.assignments.review');
        Route::post('/assignments/{assignment}/submissions/{submission}/finalize', [AssignmentController::class, 'finalizeMark'])->name('tenant.assignments.finalize');
        Route::post('/assignments/{assignment}/submissions/{submission}/ai-mark', [AssignmentController::class, 'aiMark'])->name('tenant.assignments.ai-mark');

        // Course Files
        Route::get('/files', [CourseFileController::class, 'index'])->name('tenant.files.index');
        Route::get('/files/course/{course}', [CourseFileController::class, 'manage'])->name('tenant.files.manage');
        Route::post('/files/course/{course}/folders', [CourseFileController::class, 'createFolder'])->name('tenant.files.create-folder');
        Route::put('/files/course/{course}/folders/{folder}', [CourseFileController::class, 'renameFolder'])->name('tenant.files.rename-folder');
        Route::delete('/files/course/{course}/folders/{folder}', [CourseFileController::class, 'deleteFolder'])->name('tenant.files.delete-folder');
        Route::post('/files/course/{course}/upload', [CourseFileController::class, 'upload'])->name('tenant.files.upload');
        Route::delete('/files/course/{course}/file/{file}', [CourseFileController::class, 'deleteFile'])->name('tenant.files.delete-file');
        Route::post('/files/course/{course}/file/{file}/tag', [CourseFileController::class, 'addTag'])->name('tenant.files.add-tag');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('tenant.notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('tenant.notifications.unread-count');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('tenant.notifications.mark-read');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('tenant.notifications.mark-all-read');

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('tenant.analytics.index');
        Route::get('/analytics/course/{course}', [AnalyticsController::class, 'course'])->name('tenant.analytics.course');

        // Admin Settings (tenant admin only)
        Route::get('/admin/settings', function () {
            $tenant = app('current_tenant');
            $user = auth()->user();
            if (! $user->hasRoleInTenant($tenant->id, ['admin', 'coordinator'])) {
                abort(403);
            }
            return view('tenant.placeholder', ['title' => __('nav.settings'), 'description' => 'Institution settings and configuration will be managed here.']);
        })->name('tenant.admin.settings');

        // Student routes
        Route::get('/scan', function () {
            return view('tenant.attendance.scan');
        })->name('tenant.scan');

        Route::get('/my-courses', function () {
            return view('tenant.placeholder', ['title' => __('nav.courses'), 'description' => 'Your enrolled courses will appear here.']);
        })->name('tenant.my-courses');

        Route::get('/marks', function () {
            return view('tenant.placeholder', ['title' => __('nav.marks'), 'description' => 'Your marks and feedback will be displayed here.']);
        })->name('tenant.marks');
    });

require __DIR__.'/auth.php';
