<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\AttendanceController;
use App\Http\Controllers\Tenant\CloController;
use App\Http\Controllers\Tenant\CourseController;
use App\Http\Controllers\Tenant\SectionController;
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
        Route::get('/quizzes', function () {
            return view('tenant.placeholder', ['title' => __('nav.quizzes'), 'description' => 'Live quiz sessions will be managed here.']);
        })->name('tenant.quizzes.index');

        // Assignments
        Route::get('/assignments', function () {
            return view('tenant.placeholder', ['title' => __('nav.assignments'), 'description' => 'Assignment creation and marking will be available here.']);
        })->name('tenant.assignments.index');

        // Course Files
        Route::get('/files', function () {
            return view('tenant.placeholder', ['title' => __('nav.course_files'), 'description' => 'Course file management with Google Drive sync will be available here.']);
        })->name('tenant.files.index');

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
