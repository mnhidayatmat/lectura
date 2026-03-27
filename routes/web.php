<?php

use App\Http\Controllers\Admin\AiProviderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\AnalyticsController;
use App\Http\Controllers\Tenant\AssignmentController;
use App\Http\Controllers\Tenant\AttendanceController;
use App\Http\Controllers\Tenant\QuizController;
use App\Http\Controllers\Tenant\CloController;
use App\Http\Controllers\Tenant\CourseController;
use App\Http\Controllers\Tenant\CourseFileController;
use App\Http\Controllers\Tenant\CourseMaterialController;
use App\Http\Controllers\Tenant\ActiveLearning\ActiveLearningActivityController;
use App\Http\Controllers\Tenant\ActiveLearning\ActiveLearningGroupController;
use App\Http\Controllers\Tenant\ActiveLearning\ActiveLearningPlanController;
use App\Http\Controllers\Tenant\ActiveLearning\SessionController;
use App\Http\Controllers\Tenant\ActiveLearning\StudentSessionController;
use App\Http\Controllers\Tenant\ActiveLearning\TenantAiSettingsController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\StudentCourseController;
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

    // Super admin always goes to admin panel
    if ($user->is_super_admin) {
        return redirect('/admin');
    }

    // If user has tenants, redirect to first active tenant dashboard
    $tenant = $user->activeTenants()->first();
    if ($tenant) {
        return redirect("/{$tenant->slug}/dashboard");
    }

    // No tenant — send to onboarding
    return redirect()->route('onboarding');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/onboarding', [\App\Http\Controllers\Auth\OnboardingController::class, 'show'])->middleware('auth')->name('onboarding');
Route::post('/onboarding', [\App\Http\Controllers\Auth\OnboardingController::class, 'store'])->middleware('auth')->name('onboarding.store');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Google Drive OAuth callback (outside tenant prefix since Google redirects here directly)
    Route::get('/settings/drive/callback', [\App\Http\Controllers\Tenant\SettingsController::class, 'driveCallback'])->name('settings.drive.callback');
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
        $tenants = \App\Models\Tenant::withCount([
            'tenantUsers',
            'tenantUsers as lecturers_count' => fn ($q) => $q->where('role', 'lecturer'),
            'tenantUsers as students_count' => fn ($q) => $q->where('role', 'student'),
        ])->latest()->get();
        return view('admin.tenants', compact('tenants'));
    })->name('admin.tenants');

    Route::post('/tenants', function (\Illuminate\Http\Request $request) {
        if (! auth()->user()->is_super_admin) { abort(403); }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'in:en,ms'],
        ]);
        $tenant = \App\Models\Tenant::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'timezone' => $validated['timezone'],
            'locale' => $validated['locale'],
            'is_active' => true,
            'settings' => [
                'auth' => ['allow_google_login' => true, 'sso_enabled' => false],
                'ai' => ['enabled' => true, 'provider' => 'claude'],
            ],
        ]);
        return back()->with('success', $tenant->name . ' has been created successfully.');
    })->name('admin.tenants.store');

    Route::get('/users', function () {
        if (! auth()->user()->is_super_admin) { abort(403); }
        $users = \App\Models\User::with('tenantUsers.tenant')->latest()->get();
        return view('admin.users', compact('users'));
    })->name('admin.users');

    Route::post('/users/{user}/impersonate', function (\App\Models\User $user) {
        if (! auth()->user()->is_super_admin) { abort(403); }
        session()->put('impersonator_id', auth()->id());
        auth()->login($user);
        // Redirect to first tenant dashboard or home
        $tenantUser = $user->tenantUsers()->where('is_active', true)->first();
        if ($tenantUser) {
            $tenant = $tenantUser->tenant;
            return redirect('/' . $tenant->slug . '/dashboard');
        }
        return redirect('/');
    })->name('admin.users.impersonate');

    Route::post('/impersonate/stop', function () {
        $impersonatorId = session()->pull('impersonator_id');
        if ($impersonatorId) {
            auth()->loginUsingId($impersonatorId);
        }
        return redirect()->route('admin.users');
    })->name('admin.impersonate.stop');

    Route::post('/users/{user}/toggle-pro', function (\App\Models\User $user) {
        if (! auth()->user()->is_super_admin) { abort(403); }
        $user->is_pro = ! $user->is_pro;
        $user->save();
        $status = $user->is_pro ? 'Pro' : 'Free';
        return back()->with('success', $user->name . ' is now on ' . $status . ' plan.');
    })->name('admin.users.toggle-pro');

    Route::get('/ai-usage', function (\Illuminate\Http\Request $request) {
        if (! auth()->user()->is_super_admin) { abort(403); }

        $query = \App\Models\AiUsageLog::query()->withoutGlobalScopes();
        $period = $request->input('period', '30');

        if ($period !== 'all') {
            $query->where('created_at', '>=', now()->subDays((int) $period));
        }

        $logs = $query->latest('created_at')->get();

        // Summary stats
        $totalCalls = $logs->count();
        $successCalls = $logs->where('response_status', 'success')->count();
        $failedCalls = $logs->where('response_status', 'failed')->count();
        $totalInputTokens = $logs->sum('input_tokens');
        $totalOutputTokens = $logs->sum('output_tokens');
        $totalCost = $logs->sum('cost_usd');
        $avgDuration = $logs->avg('duration_ms');

        // By module
        $byModule = $logs->groupBy('module')->map(fn ($items) => [
            'calls' => $items->count(),
            'input_tokens' => $items->sum('input_tokens'),
            'output_tokens' => $items->sum('output_tokens'),
            'cost' => $items->sum('cost_usd'),
        ])->sortByDesc('calls');

        // By provider
        $byProvider = $logs->groupBy('provider')->map(fn ($items) => [
            'calls' => $items->count(),
            'input_tokens' => $items->sum('input_tokens'),
            'output_tokens' => $items->sum('output_tokens'),
            'cost' => $items->sum('cost_usd'),
        ])->sortByDesc('calls');

        // By tenant
        $tenantIds = $logs->pluck('tenant_id')->unique()->filter();
        $tenants = \App\Models\Tenant::whereIn('id', $tenantIds)->pluck('name', 'id');
        $byTenant = $logs->groupBy('tenant_id')->map(fn ($items, $tenantId) => [
            'name' => $tenants[$tenantId] ?? 'Unknown',
            'calls' => $items->count(),
            'tokens' => $items->sum('input_tokens') + $items->sum('output_tokens'),
            'cost' => $items->sum('cost_usd'),
        ])->sortByDesc('calls');

        // Recent logs with user info
        $recentLogs = \App\Models\AiUsageLog::query()->withoutGlobalScopes()
            ->with('user')
            ->latest('created_at')
            ->limit(50)
            ->get();
        $recentTenants = \App\Models\Tenant::whereIn('id', $recentLogs->pluck('tenant_id')->unique()->filter())->pluck('name', 'id');

        return view('admin.ai-usage', compact(
            'period', 'totalCalls', 'successCalls', 'failedCalls',
            'totalInputTokens', 'totalOutputTokens', 'totalCost', 'avgDuration',
            'byModule', 'byProvider', 'byTenant', 'recentLogs', 'recentTenants'
        ));
    })->name('admin.ai-usage');

    // AI Provider Settings
    Route::get('/ai-settings', [AiProviderController::class, 'index'])->name('admin.ai-settings');
    Route::post('/ai-settings', [AiProviderController::class, 'store'])->name('admin.ai-settings.store');
    Route::put('/ai-settings/{aiProvider}', [AiProviderController::class, 'update'])->name('admin.ai-settings.update');
    Route::delete('/ai-settings/{aiProvider}', [AiProviderController::class, 'destroy'])->name('admin.ai-settings.destroy');
    Route::post('/ai-settings/{aiProvider}/test', [AiProviderController::class, 'testConnection'])->name('admin.ai-settings.test');

    Route::get('/activity', function () {
        if (! auth()->user()->is_super_admin) { abort(403); }

        $query = \Spatie\Activitylog\Models\Activity::with('causer', 'subject')
            ->latest();

        // Filter by log name
        if (request('log')) {
            $query->where('log_name', request('log'));
        }

        // Filter by event
        if (request('event')) {
            $query->where('event', request('event'));
        }

        $activities = $query->paginate(50)->withQueryString();

        $logNames = \Spatie\Activitylog\Models\Activity::distinct()->pluck('log_name')->filter()->sort()->values();
        $events = \Spatie\Activitylog\Models\Activity::distinct()->pluck('event')->filter()->sort()->values();

        return view('admin.activity', compact('activities', 'logNames', 'events'));
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

        // Random Present Student Wheel
        Route::get('/random-wheel', [\App\Http\Controllers\Tenant\RandomWheelController::class, 'index'])->name('tenant.random-wheel');
        Route::get('/random-wheel/sessions', [\App\Http\Controllers\Tenant\RandomWheelController::class, 'sessions'])->name('tenant.random-wheel.sessions');
        Route::get('/random-wheel/present-students', [\App\Http\Controllers\Tenant\RandomWheelController::class, 'presentStudents'])->name('tenant.random-wheel.present-students');

        // Attendance
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('tenant.attendance.index');
        Route::post('/attendance/start', [AttendanceController::class, 'start'])->name('tenant.attendance.start');
        Route::get('/attendance/{session}/qr', [AttendanceController::class, 'qr'])->name('tenant.attendance.qr');
        Route::get('/attendance/{session}/token', [AttendanceController::class, 'refreshToken'])->name('tenant.attendance.token');
        Route::post('/attendance/{session}/end', [AttendanceController::class, 'end'])->name('tenant.attendance.end');
        Route::get('/attendance/{session}', [AttendanceController::class, 'show'])->name('tenant.attendance.show');
        Route::put('/attendance/{session}/records/{record}', [AttendanceController::class, 'override'])->name('tenant.attendance.override');
        Route::delete('/attendance/{session}', [AttendanceController::class, 'destroy'])->name('tenant.attendance.destroy');

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

        // Course Materials (Lecturer)
        Route::get('/materials', [CourseMaterialController::class, 'index'])->name('tenant.materials.index');
        Route::get('/materials/course/{course}', [CourseMaterialController::class, 'manage'])->name('tenant.materials.manage');
        Route::post('/materials/course/{course}/upload', [CourseMaterialController::class, 'upload'])->name('tenant.materials.upload');
        Route::post('/materials/course/{course}/link', [CourseMaterialController::class, 'storeLink'])->name('tenant.materials.store-link');
        Route::delete('/materials/course/{course}/{file}', [CourseMaterialController::class, 'destroy'])->name('tenant.materials.destroy');
        Route::get('/materials/course/{course}/file/{file}/download', [CourseMaterialController::class, 'download'])->name('tenant.materials.download');

        // Course Materials (Student)
        Route::get('/my-materials', [CourseMaterialController::class, 'studentIndex'])->name('tenant.materials.student-index');
        Route::get('/my-materials/course/{course}', [CourseMaterialController::class, 'studentCourse'])->name('tenant.materials.student-course');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('tenant.notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('tenant.notifications.unread-count');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('tenant.notifications.mark-read');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('tenant.notifications.mark-all-read');

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('tenant.analytics.index');
        Route::get('/analytics/course/{course}', [AnalyticsController::class, 'course'])->name('tenant.analytics.course');

        // Active Learning — standalone listing (all courses)
        Route::get('/active-learning', [ActiveLearningPlanController::class, 'all'])->name('tenant.active-learning.all');

        // Active Learning Plans (per-course)
        Route::prefix('courses/{course}/active-learning')->group(function () {
            Route::get('/', [ActiveLearningPlanController::class, 'index'])->name('tenant.active-learning.index');
            Route::get('/create', [ActiveLearningPlanController::class, 'create'])->name('tenant.active-learning.create');
            Route::post('/', [ActiveLearningPlanController::class, 'store'])->name('tenant.active-learning.store');
            Route::get('/{plan}', [ActiveLearningPlanController::class, 'show'])->name('tenant.active-learning.show');
            Route::get('/{plan}/edit', [ActiveLearningPlanController::class, 'edit'])->name('tenant.active-learning.edit');
            Route::put('/{plan}', [ActiveLearningPlanController::class, 'update'])->name('tenant.active-learning.update');
            Route::delete('/{plan}', [ActiveLearningPlanController::class, 'destroy'])->name('tenant.active-learning.destroy');
            Route::post('/{plan}/publish', [ActiveLearningPlanController::class, 'publish'])->name('tenant.active-learning.publish');

            // AI generation (Pro)
            Route::post('/{plan}/generate-ai', [ActiveLearningPlanController::class, 'generateAi'])->name('tenant.active-learning.generate-ai');
            Route::get('/{plan}/generation-status', [ActiveLearningPlanController::class, 'generationStatus'])->name('tenant.active-learning.generation-status');

            // Activities
            Route::post('/{plan}/activities', [ActiveLearningActivityController::class, 'store'])->name('tenant.active-learning.activities.store');
            Route::put('/{plan}/activities/{activity}', [ActiveLearningActivityController::class, 'update'])->name('tenant.active-learning.activities.update');
            Route::delete('/{plan}/activities/{activity}', [ActiveLearningActivityController::class, 'destroy'])->name('tenant.active-learning.activities.destroy');
            Route::post('/{plan}/activities/reorder', [ActiveLearningActivityController::class, 'reorder'])->name('tenant.active-learning.activities.reorder');

            // Groups
            Route::post('/{plan}/activities/{activity}/groups', [ActiveLearningGroupController::class, 'store'])->name('tenant.active-learning.groups.store');
            Route::delete('/{plan}/activities/{activity}/groups/{group}', [ActiveLearningGroupController::class, 'destroy'])->name('tenant.active-learning.groups.destroy');
            Route::post('/{plan}/activities/{activity}/groups/{group}/members', [ActiveLearningGroupController::class, 'addMember'])->name('tenant.active-learning.groups.members.add');
            Route::delete('/{plan}/activities/{activity}/groups/{group}/members/{user}', [ActiveLearningGroupController::class, 'removeMember'])->name('tenant.active-learning.groups.members.remove');
            Route::post('/{plan}/activities/{activity}/groups/arrange-attendance', [ActiveLearningGroupController::class, 'arrangeFromAttendance'])->name('tenant.active-learning.groups.arrange-attendance');
            Route::post('/{plan}/activities/{activity}/groups/arrange-ai', [ActiveLearningGroupController::class, 'arrangeWithAi'])->name('tenant.active-learning.groups.arrange-ai');

            // Live Sessions (lecturer)
            Route::post('/{plan}/sessions', [SessionController::class, 'start'])->name('tenant.active-learning.sessions.start');
            Route::get('/{plan}/sessions/{session}', [SessionController::class, 'dashboard'])->name('tenant.active-learning.sessions.dashboard');
            Route::post('/{plan}/sessions/{session}/advance', [SessionController::class, 'advance'])->name('tenant.active-learning.sessions.advance');
            Route::post('/{plan}/sessions/{session}/end', [SessionController::class, 'end'])->name('tenant.active-learning.sessions.end');
            Route::get('/{plan}/sessions/{session}/state', [SessionController::class, 'state'])->name('tenant.active-learning.sessions.state');
            Route::get('/{plan}/sessions/{session}/summary', [SessionController::class, 'summary'])->name('tenant.active-learning.sessions.summary');
        });

        // Student Live Session Routes
        Route::get('/session/join', [StudentSessionController::class, 'joinForm'])->name('tenant.session.join');
        Route::post('/session/join', [StudentSessionController::class, 'joinByCode'])->name('tenant.session.join.process');
        Route::get('/session/{session}/live', [StudentSessionController::class, 'live'])->name('tenant.session.live');
        Route::post('/session/{session}/respond', [StudentSessionController::class, 'respond'])->name('tenant.session.respond');
        Route::get('/session/{session}/state', [StudentSessionController::class, 'state'])->name('tenant.session.student-state');
        Route::get('/session/{session}/review', [StudentSessionController::class, 'review'])->name('tenant.session.review');

        // Tenant AI Settings (Pro)
        Route::get('/admin/ai-settings', [TenantAiSettingsController::class, 'edit'])->name('tenant.admin.ai-settings');
        Route::put('/admin/ai-settings', [TenantAiSettingsController::class, 'update'])->name('tenant.admin.ai-settings.update');
        Route::post('/admin/ai-settings/test', [TenantAiSettingsController::class, 'testConnection'])->name('tenant.admin.ai-settings.test');

        // Admin Settings (tenant admin only)
        Route::get('/admin/settings', function () {
            $tenant = app('current_tenant');
            $user = auth()->user();
            if (! $user->hasRoleInTenant($tenant->id, ['admin', 'coordinator'])) {
                abort(403);
            }
            return view('tenant.placeholder', ['title' => __('nav.settings'), 'description' => 'Institution settings and configuration will be managed here.']);
        })->name('tenant.admin.settings');

        // Role Switcher
        Route::post('/switch-role', [\App\Http\Controllers\Tenant\RoleSwitchController::class, 'switch'])->name('tenant.switch-role');

        // Student routes
        Route::get('/scan', function () {
            return view('tenant.attendance.scan');
        })->name('tenant.scan');

        Route::get('/my-courses', [StudentCourseController::class, 'index'])->name('tenant.my-courses');
        Route::get('/my-courses/{course}', [StudentCourseController::class, 'show'])->name('tenant.my-courses.show');
        Route::post('/my-courses/enroll', [StudentCourseController::class, 'enroll'])->name('tenant.my-courses.enroll');

        Route::get('/marks', [\App\Http\Controllers\Tenant\StudentMarkController::class, 'index'])->name('tenant.marks');
        Route::get('/marks/{mark}', [\App\Http\Controllers\Tenant\StudentMarkController::class, 'show'])->name('tenant.marks.show');

        // Settings
        Route::get('/settings', [\App\Http\Controllers\Tenant\SettingsController::class, 'index'])->name('tenant.settings');
        Route::get('/settings/drive/connect', [\App\Http\Controllers\Tenant\SettingsController::class, 'connectDrive'])->name('tenant.settings.drive.connect');
        Route::post('/settings/drive/disconnect', [\App\Http\Controllers\Tenant\SettingsController::class, 'disconnectDrive'])->name('tenant.settings.drive.disconnect');
    });

require __DIR__.'/auth.php';
