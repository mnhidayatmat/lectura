<?php

use App\Http\Controllers\McpController;
use App\Http\Controllers\McpOAuthController;
use App\Http\Controllers\Admin\AiProviderController;
use App\Http\Controllers\ProfileController;
// AnalyticsController removed — analytics routes now redirect to PerformanceController
use App\Http\Controllers\Tenant\AssignmentController;
use App\Http\Controllers\Tenant\AttendanceController;
use App\Http\Controllers\Tenant\AttendanceExcuseController;
use App\Http\Controllers\Tenant\AttendancePolicyController;
use App\Http\Controllers\Tenant\AttendanceReportController;
use App\Http\Controllers\Tenant\QuizController;
use App\Http\Controllers\Tenant\StudentGroupController;
use App\Http\Controllers\Tenant\CloController;
use App\Http\Controllers\Tenant\CourseController;
use App\Http\Controllers\Tenant\CourseFileController;
use App\Http\Controllers\Tenant\CourseMaterialController;
use App\Http\Controllers\Tenant\Assessment\AssessmentPlanController;
use App\Http\Controllers\Tenant\Assessment\AssessmentItemController;
use App\Http\Controllers\Tenant\Assessment\AssessmentReportController;
use App\Http\Controllers\Tenant\Assessment\AssessmentScoreController;
use App\Http\Controllers\Tenant\Assessment\PloController;
use App\Http\Controllers\Tenant\Assessment\CloPlOMapController;
use App\Http\Controllers\Tenant\ActiveLearning\ActiveLearningActivityController;
use App\Http\Controllers\Tenant\ActiveLearning\ActiveLearningGroupController;
use App\Http\Controllers\Tenant\ActiveLearning\ActiveLearningPlanController;
use App\Http\Controllers\Tenant\ActiveLearning\SessionController;
use App\Http\Controllers\Tenant\ActiveLearning\StudentSessionController;
use App\Http\Controllers\Tenant\ActiveLearning\TenantAiSettingsController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\PerformanceController;
use App\Http\Controllers\Tenant\StudentAttendanceController;
use App\Http\Controllers\Tenant\StudentCourseController;
use App\Http\Controllers\Tenant\SectionController;
use App\Http\Controllers\Tenant\TeachingPlanController;
use App\Http\Controllers\Tenant\TopicController;
use Illuminate\Support\Facades\Route;

// ── MCP OAuth 2.0 discovery (RFC 9728 + RFC 8414) ──────────────────────────────
Route::get('/.well-known/oauth-protected-resource', [McpOAuthController::class, 'protectedResourceMetadata']);
Route::get('/.well-known/oauth-protected-resource/{path}', [McpOAuthController::class, 'protectedResourceMetadata'])
    ->where('path', '.*');
Route::get('/.well-known/oauth-authorization-server', [McpOAuthController::class, 'metadata'])
    ->name('mcp.oauth.metadata');

// ── MCP OAuth 2.0 endpoints (no CSRF, no auth middleware) ────────────────────
Route::get('/authorize', [McpOAuthController::class, 'authorize'])->name('mcp.oauth.authorize');
Route::options('/authorize', [McpOAuthController::class, 'preflight']);
Route::options('/oauth/token', [McpOAuthController::class, 'preflight']);
Route::post('/oauth/token', [McpOAuthController::class, 'token'])
    ->middleware('throttle:20,1')
    ->name('mcp.oauth.token');
Route::options('/oauth/register', [McpOAuthController::class, 'preflight']);
Route::post('/oauth/register', [McpOAuthController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('mcp.oauth.register');

// ── MCP Server (Bearer-token authenticated, no CSRF) ──────────────────────────
Route::get('/mcp', [McpController::class, 'discover'])->name('mcp.discover');
Route::options('/mcp', [McpController::class, 'preflight']);
Route::post('/mcp', [McpController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('mcp.handle');

// ── Public ──
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

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

    // Editor image upload (tiptap paste/drop)
    Route::post('/editor/upload-image', [\App\Http\Controllers\EditorImageController::class, 'upload'])->name('editor.upload-image');

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
            $todaySchedule = collect();

            if ($role !== 'student') {
                $courses = \App\Models\Course::where('lecturer_id', $user->id)
                    ->withCount('sections')
                    ->latest()->get();
                $courseCount = $courses->count();
                $sectionIds = \App\Models\Section::whereIn('course_id', $courses->pluck('id'))->pluck('id');
                $studentCount = \App\Models\SectionStudent::whereIn('section_id', $sectionIds)->where('is_active', true)->distinct('user_id')->count('user_id');

                // Today's schedule from section schedules
                $today = strtolower(now()->format('l')); // e.g. "monday"
                $sections = \App\Models\Section::whereIn('course_id', $courses->pluck('id'))
                    ->whereNotNull('schedule')
                    ->where('is_active', true)
                    ->with('course:id,code,title')
                    ->get();

                $todaySchedule = $sections->flatMap(function ($section) use ($today) {
                    $slots = collect($section->schedule ?? [])
                        ->filter(fn ($slot) => ($slot['day'] ?? '') === $today);
                    return $slots->map(fn ($slot) => (object) [
                        'course_code' => $section->course->code,
                        'course_title' => $section->course->title,
                        'section_name' => $section->name,
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                        'location' => $slot['location'] ?? null,
                        'type' => $slot['type'] ?? 'lecture',
                        'course_id' => $section->course_id,
                    ]);
                })->sortBy('start_time')->values();
            }

            return view('tenant.dashboard', compact('tenant', 'role', 'courseCount', 'studentCount', 'courses', 'todaySchedule'));
        })->name('tenant.dashboard');

        // Course Management
        Route::get('/courses', [CourseController::class, 'index'])->name('tenant.courses.index');
        Route::post('/courses/join', [CourseController::class, 'joinCourse'])->name('tenant.courses.join');
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
        Route::put('/courses/{course}/sections/{section}', [SectionController::class, 'update'])->name('tenant.courses.sections.update');
        Route::post('/courses/{course}/sections/{section}/toggle-active', [SectionController::class, 'toggleActive'])->name('tenant.courses.sections.toggle-active');
        Route::post('/courses/{course}/sections/{section}/students', [SectionController::class, 'addStudent'])->name('tenant.courses.sections.students.add');
        Route::post('/courses/{course}/sections/{section}/students/import', [SectionController::class, 'importCsv'])->name('tenant.courses.sections.students.import');
        Route::delete('/courses/{course}/sections/{section}/students/{user}', [SectionController::class, 'removeStudent'])->name('tenant.courses.sections.students.remove');
        Route::put('/courses/{course}/sections/{section}/schedule', [SectionController::class, 'updateSchedule'])->name('tenant.courses.sections.schedule.update');

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
        Route::post('/attendance/{session}/reopen', [AttendanceController::class, 'reopen'])->name('tenant.attendance.reopen');
        Route::get('/attendance/{session}', [AttendanceController::class, 'show'])->name('tenant.attendance.show');
        Route::put('/attendance/{session}/records/{record}', [AttendanceController::class, 'override'])->name('tenant.attendance.override');
        Route::delete('/attendance/{session}', [AttendanceController::class, 'destroy'])->name('tenant.attendance.destroy');

        // Student check-in API
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('tenant.attendance.checkin');

        // Attendance Excuses (lecturer review)
        Route::get('/attendance/excuses', [AttendanceExcuseController::class, 'index'])->name('tenant.attendance.excuses');
        Route::put('/attendance/excuses/{excuse}/approve', [AttendanceExcuseController::class, 'approve'])->name('tenant.attendance.excuses.approve');
        Route::put('/attendance/excuses/{excuse}/reject', [AttendanceExcuseController::class, 'reject'])->name('tenant.attendance.excuses.reject');
        Route::get('/attendance/excuses/{excuse}/attachment', [AttendanceExcuseController::class, 'downloadAttachment'])->name('tenant.attendance.excuses.attachment');

        // Attendance Reports
        Route::get('/attendance/report/{course}', [AttendanceReportController::class, 'show'])->name('tenant.attendance.report');
        Route::get('/attendance/report/{course}/pdf', [AttendanceReportController::class, 'downloadPdf'])->name('tenant.attendance.report.pdf');
        Route::get('/attendance/report/{course}/excel', [AttendanceReportController::class, 'downloadExcel'])->name('tenant.attendance.report.excel');
        Route::post('/attendance/report/{course}/sync-files', [AttendanceReportController::class, 'syncToCourseFiles'])->name('tenant.attendance.report.sync');

        // Attendance Policy (per-course)
        Route::get('/courses/{course}/attendance-policy', [AttendancePolicyController::class, 'edit'])->name('tenant.courses.attendance-policy.edit');
        Route::put('/courses/{course}/attendance-policy', [AttendancePolicyController::class, 'update'])->name('tenant.courses.attendance-policy.update');

        // Student Attendance (student-facing)
        Route::get('/my-attendance', [StudentAttendanceController::class, 'index'])->name('tenant.my-attendance');
        Route::get('/my-attendance/course/{course}', [StudentAttendanceController::class, 'course'])->name('tenant.my-attendance.course');
        Route::post('/my-attendance/excuse/{record}', [StudentAttendanceController::class, 'submitExcuse'])->name('tenant.my-attendance.excuse.submit');

        // Quizzes (Live & Offline)
        Route::get('/quizzes', [QuizController::class, 'index'])->name('tenant.quizzes.index');
        Route::get('/quizzes/create', [QuizController::class, 'create'])->name('tenant.quizzes.create');
        Route::post('/quizzes', [QuizController::class, 'store'])->name('tenant.quizzes.store');
        // Quiz Folders
        Route::post('/quizzes/folders', [QuizController::class, 'storeFolder'])->name('tenant.quizzes.folders.store');
        Route::put('/quizzes/folders/{folder}', [QuizController::class, 'updateFolder'])->name('tenant.quizzes.folders.update');
        Route::delete('/quizzes/folders/{folder}', [QuizController::class, 'destroyFolder'])->name('tenant.quizzes.folders.destroy');
        Route::post('/quizzes/{session}/move', [QuizController::class, 'moveToFolder'])->name('tenant.quizzes.move');
        Route::get('/quizzes/{session}/control', [QuizController::class, 'control'])->name('tenant.quizzes.control');
        Route::post('/quizzes/{session}/start', [QuizController::class, 'start'])->name('tenant.quizzes.start');
        Route::post('/quizzes/{session}/close', [QuizController::class, 'closeQuestion'])->name('tenant.quizzes.close');
        Route::post('/quizzes/{session}/next', [QuizController::class, 'nextQuestion'])->name('tenant.quizzes.next');
        Route::post('/quizzes/{session}/end', [QuizController::class, 'end'])->name('tenant.quizzes.end');
        Route::get('/quizzes/{session}/results', [QuizController::class, 'results'])->name('tenant.quizzes.results');
        Route::get('/quizzes/{session}/edit', [QuizController::class, 'edit'])->name('tenant.quizzes.edit');
        Route::put('/quizzes/{session}', [QuizController::class, 'update'])->name('tenant.quizzes.update');
        Route::post('/quizzes/{session}/replay', [QuizController::class, 'replay'])->name('tenant.quizzes.replay');
        Route::delete('/quizzes/{session}', [QuizController::class, 'destroy'])->name('tenant.quizzes.destroy');
        Route::get('/quizzes/{session}/state', [QuizController::class, 'state'])->name('tenant.quizzes.state');
        // Student quiz
        Route::get('/quiz/join', [QuizController::class, 'join'])->name('tenant.quizzes.join');
        Route::get('/quiz/{session}/play', [QuizController::class, 'play'])->name('tenant.quizzes.play');
        Route::post('/quiz/{session}/respond', [QuizController::class, 'respond'])->name('tenant.quizzes.respond');
        Route::get('/quiz/{session}/student-state', [QuizController::class, 'studentState'])->name('tenant.quizzes.student-state');
        Route::post('/quiz/{session}/submit-offline', [QuizController::class, 'submitOffline'])->name('tenant.quizzes.submit-offline');
        Route::get('/quiz/{session}/offline-result', [QuizController::class, 'offlineResult'])->name('tenant.quizzes.offlineResult');

        // Assignments
        Route::get('/assignments', [AssignmentController::class, 'index'])->name('tenant.assignments.index');
        Route::get('/assignments/create', [AssignmentController::class, 'create'])->name('tenant.assignments.create');
        Route::get('/assignments/{assignment}/sub/create', [AssignmentController::class, 'create'])->name('tenant.assignments.sub.create');
        Route::post('/assignments', [AssignmentController::class, 'store'])->name('tenant.assignments.store');
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('tenant.assignments.show');
        Route::post('/assignments/{assignment}/publish', [AssignmentController::class, 'publish'])->name('tenant.assignments.publish');
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('tenant.assignments.destroy');
        Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->name('tenant.assignments.submit');
        Route::get('/assignments/{assignment}/submissions/{submission}', [AssignmentController::class, 'review'])->name('tenant.assignments.review');
        Route::post('/assignments/{assignment}/submissions/{submission}/finalize', [AssignmentController::class, 'finalizeMark'])->name('tenant.assignments.finalize');
        Route::post('/assignments/{assignment}/submissions/{submission}/ai-mark', [AssignmentController::class, 'aiMark'])->name('tenant.assignments.ai-mark');

        // Assessment (Course Assessment Plan)
        Route::get('/assessments', [AssessmentPlanController::class, 'overview'])->name('tenant.assessments.overview');
        Route::prefix('courses/{course}/assessments')->group(function () {
            Route::get('/', [AssessmentPlanController::class, 'index'])->name('tenant.assessments.index');
            Route::get('/create', [AssessmentPlanController::class, 'create'])->name('tenant.assessments.create');
            Route::get('/{assessment}/child/create', [AssessmentPlanController::class, 'create'])->name('tenant.assessments.child.create');
            Route::post('/', [AssessmentPlanController::class, 'store'])->name('tenant.assessments.store');
            Route::get('/{assessment}/edit', [AssessmentPlanController::class, 'edit'])->name('tenant.assessments.edit');
            Route::put('/{assessment}', [AssessmentPlanController::class, 'update'])->name('tenant.assessments.update');
            Route::delete('/{assessment}', [AssessmentPlanController::class, 'destroy'])->name('tenant.assessments.destroy');

            Route::post('/{assessment}/items', [AssessmentItemController::class, 'store'])->name('tenant.assessments.items.store');
            Route::delete('/{assessment}/items/{item}', [AssessmentItemController::class, 'destroy'])->name('tenant.assessments.items.destroy');

            // Scores
            Route::get('/{assessment}/scores', [AssessmentScoreController::class, 'index'])->name('tenant.assessments.scores.index');
            Route::post('/{assessment}/scores/compute', [AssessmentScoreController::class, 'compute'])->name('tenant.assessments.scores.compute');
            Route::get('/{assessment}/scores/manual', [AssessmentScoreController::class, 'manualEntry'])->name('tenant.assessments.scores.manual');
            Route::post('/{assessment}/scores/manual', [AssessmentScoreController::class, 'storeManual'])->name('tenant.assessments.scores.store-manual');

            // Submissions
            Route::get('/{assessment}/submissions', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'index'])->name('tenant.assessments.submissions.index');
            Route::get('/{assessment}/submissions/{submission}', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'show'])->name('tenant.assessments.submissions.show');
            Route::post('/{assessment}/submissions/{submission}/mark', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'storeMark'])->name('tenant.assessments.submissions.mark');
            Route::post('/{assessment}/scores/release', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'release'])->name('tenant.assessments.scores.release');
            Route::post('/{assessment}/scores/{score}/unrelease', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'unrelease'])->name('tenant.assessments.scores.unrelease');
            Route::get('/{assessment}/submissions/files/{file}/download', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'downloadFile'])->name('tenant.assessments.submissions.download');
        });

        // Assessment Reports
        Route::get('/courses/{course}/assessment-reports', [AssessmentReportController::class, 'courseReport'])->name('tenant.assessment-reports.course');

        // PLO Management
        Route::get('/programmes/{programme}/plos', [PloController::class, 'index'])->name('tenant.plos.index');
        Route::post('/programmes/{programme}/plos', [PloController::class, 'store'])->name('tenant.plos.store');
        Route::put('/programmes/{programme}/plos/{plo}', [PloController::class, 'update'])->name('tenant.plos.update');
        Route::delete('/programmes/{programme}/plos/{plo}', [PloController::class, 'destroy'])->name('tenant.plos.destroy');

        // CLO-PLO Mapping
        Route::get('/courses/{course}/clo-plo', [CloPlOMapController::class, 'edit'])->name('tenant.clo-plo.edit');
        Route::put('/courses/{course}/clo-plo', [CloPlOMapController::class, 'update'])->name('tenant.clo-plo.update');

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
        Route::post('/materials/course/{course}/sections', [CourseMaterialController::class, 'storeSection'])->name('tenant.materials.sections.store');
        Route::patch('/materials/course/{course}/sections/{section}', [CourseMaterialController::class, 'updateSection'])->name('tenant.materials.sections.update');
        Route::delete('/materials/course/{course}/sections/{section}', [CourseMaterialController::class, 'destroySection'])->name('tenant.materials.sections.destroy');
        Route::post('/materials/course/{course}/sections/{section}/move', [CourseMaterialController::class, 'moveSection'])->name('tenant.materials.sections.move');
        Route::post('/materials/course/{course}/upload', [CourseMaterialController::class, 'upload'])->name('tenant.materials.upload');
        Route::post('/materials/course/{course}/link', [CourseMaterialController::class, 'storeLink'])->name('tenant.materials.store-link');
        Route::patch('/materials/course/{course}/{file}', [CourseMaterialController::class, 'updateMaterial'])->name('tenant.materials.update');
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

        // Analytics (redirects to Performance)
        Route::get('/analytics', fn () => redirect()->route('tenant.performance.index', app('current_tenant')->slug))->name('tenant.analytics.index');
        Route::get('/analytics/course/{course}', fn (string $t, \App\Models\Course $course) => redirect()->route('tenant.performance.course', [app('current_tenant')->slug, $course]))->name('tenant.analytics.course');

        // Performance Tracking (Lecturer)
        Route::get('/performance', [PerformanceController::class, 'lecturerIndex'])->name('tenant.performance.index');
        Route::get('/performance/course/{course}', [PerformanceController::class, 'lecturerCourse'])->name('tenant.performance.course');
        Route::get('/performance/course/{course}/student/{student}', [PerformanceController::class, 'lecturerStudent'])->name('tenant.performance.student');
        Route::post('/performance/course/{course}/ai-suggestions', [PerformanceController::class, 'generateAiSuggestions'])->name('tenant.performance.ai-generate');
        Route::get('/performance/course/{course}/ai-status', [PerformanceController::class, 'aiSuggestionStatus'])->name('tenant.performance.ai-status');

        // Performance Tracking (Student)
        Route::get('/my-performance', [PerformanceController::class, 'studentIndex'])->name('tenant.my-performance');
        Route::get('/my-performance/course/{course}', [PerformanceController::class, 'studentCourse'])->name('tenant.my-performance.course');

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
            Route::post('/{plan}/accept-ai-draft', [ActiveLearningPlanController::class, 'acceptAiDraft'])->name('tenant.active-learning.accept-ai-draft');
            Route::post('/{plan}/discard-ai-draft', [ActiveLearningPlanController::class, 'discardAiDraft'])->name('tenant.active-learning.discard-ai-draft');

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
        Route::get('/live', [StudentSessionController::class, 'hub'])->name('tenant.live-hub');
        Route::get('/session/join', [StudentSessionController::class, 'joinForm'])->name('tenant.session.join');
        Route::post('/session/join', [StudentSessionController::class, 'joinByCode'])->name('tenant.session.join.process');
        Route::get('/session/{session}/live', [StudentSessionController::class, 'live'])->name('tenant.session.live');
        Route::post('/session/{session}/respond', [StudentSessionController::class, 'respond'])->name('tenant.session.respond');
        Route::get('/session/{session}/state', [StudentSessionController::class, 'state'])->name('tenant.session.student-state');
        Route::get('/session/{session}/review', [StudentSessionController::class, 'review'])->name('tenant.session.review');

        // Student Groups (per-course)
        Route::prefix('courses/{course}/groups')->group(function () {
            Route::get('/', [StudentGroupController::class, 'index'])->name('tenant.student-groups.index');
            Route::get('/create', [StudentGroupController::class, 'create'])->name('tenant.student-groups.create');
            Route::post('/', [StudentGroupController::class, 'store'])->name('tenant.student-groups.store');
            Route::get('/{set}', [StudentGroupController::class, 'show'])->name('tenant.student-groups.show');
            Route::delete('/{set}', [StudentGroupController::class, 'destroy'])->name('tenant.student-groups.destroy');
            Route::post('/{set}/groups', [StudentGroupController::class, 'storeGroup'])->name('tenant.student-groups.groups.store');
            Route::delete('/{set}/groups/{group}', [StudentGroupController::class, 'destroyGroup'])->name('tenant.student-groups.groups.destroy');
            Route::post('/{set}/groups/{group}/members', [StudentGroupController::class, 'addMember'])->name('tenant.student-groups.members.add');
            Route::delete('/{set}/groups/{group}/members/{user}', [StudentGroupController::class, 'removeMember'])->name('tenant.student-groups.members.remove');
            Route::post('/{set}/arrange-random', [StudentGroupController::class, 'arrangeRandom'])->name('tenant.student-groups.arrange-random');
        });
        Route::get('/my-groups', [StudentGroupController::class, 'myGroups'])->name('tenant.student-groups.my-index');

        // Group Workspace
        Route::prefix('workspace')->name('tenant.workspace.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Tenant\Workspace\WorkspaceController::class, 'index'])->name('index');
            Route::get('/{group}', [\App\Http\Controllers\Tenant\Workspace\WorkspaceController::class, 'show'])->name('show');
            Route::post('/{group}/project', [\App\Http\Controllers\Tenant\Workspace\WorkspaceController::class, 'updateProject'])->name('project.update');
            Route::post('/{group}/score', [\App\Http\Controllers\Tenant\Workspace\WorkspaceController::class, 'updateScore'])->name('score.update');

            // Chat
            Route::get('/{group}/chat', [\App\Http\Controllers\Tenant\Workspace\WorkspaceChatController::class, 'index'])->name('chat.index');
            Route::post('/{group}/chat', [\App\Http\Controllers\Tenant\Workspace\WorkspaceChatController::class, 'store'])->name('chat.store');
            Route::patch('/{group}/chat/{message}', [\App\Http\Controllers\Tenant\Workspace\WorkspaceChatController::class, 'update'])->name('chat.update');
            Route::delete('/{group}/chat/{message}', [\App\Http\Controllers\Tenant\Workspace\WorkspaceChatController::class, 'destroy'])->name('chat.destroy');
            Route::post('/{group}/chat/presence', [\App\Http\Controllers\Tenant\Workspace\WorkspaceChatController::class, 'presence'])->name('chat.presence');

            // Files & Folders
            Route::post('/{group}/files', [\App\Http\Controllers\Tenant\Workspace\WorkspaceFileController::class, 'store'])->name('files.store');
            Route::delete('/{group}/files/{file}', [\App\Http\Controllers\Tenant\Workspace\WorkspaceFileController::class, 'destroy'])->name('files.destroy');
            Route::get('/{group}/files/{file}/download', [\App\Http\Controllers\Tenant\Workspace\WorkspaceFileController::class, 'download'])->name('files.download');
            Route::post('/{group}/folders', [\App\Http\Controllers\Tenant\Workspace\WorkspaceFileController::class, 'storeFolder'])->name('folders.store');
            Route::delete('/{group}/folders/{folder}', [\App\Http\Controllers\Tenant\Workspace\WorkspaceFileController::class, 'destroyFolder'])->name('folders.destroy');

            // Tasks
            Route::post('/{group}/tasks', [\App\Http\Controllers\Tenant\Workspace\WorkspaceTaskController::class, 'store'])->name('tasks.store');
            Route::patch('/{group}/tasks/{task}', [\App\Http\Controllers\Tenant\Workspace\WorkspaceTaskController::class, 'update'])->name('tasks.update');
            Route::delete('/{group}/tasks/{task}', [\App\Http\Controllers\Tenant\Workspace\WorkspaceTaskController::class, 'destroy'])->name('tasks.destroy');

            // Minutes
            Route::post('/{group}/minutes', [\App\Http\Controllers\Tenant\Workspace\WorkspaceMinuteController::class, 'store'])->name('minutes.store');
            Route::delete('/{group}/minutes/{minute}', [\App\Http\Controllers\Tenant\Workspace\WorkspaceMinuteController::class, 'destroy'])->name('minutes.destroy');

            // Sleeping Partner Reports
            Route::post('/{group}/reports', [\App\Http\Controllers\Tenant\Workspace\WorkspaceReportController::class, 'store'])->name('reports.store');

            // Voting
            Route::post('/{group}/votes/start', [\App\Http\Controllers\Tenant\Workspace\WorkspaceVoteController::class, 'start'])->name('votes.start');
            Route::post('/{group}/votes/{round}/cast', [\App\Http\Controllers\Tenant\Workspace\WorkspaceVoteController::class, 'cast'])->name('votes.cast');
            Route::post('/{group}/votes/{round}/close', [\App\Http\Controllers\Tenant\Workspace\WorkspaceVoteController::class, 'close'])->name('votes.close');
            Route::delete('/{group}/votes/{round}', [\App\Http\Controllers\Tenant\Workspace\WorkspaceVoteController::class, 'destroy'])->name('votes.destroy');

            // Member Swap
            Route::post('/{group}/swaps', [\App\Http\Controllers\Tenant\Workspace\WorkspaceSwapController::class, 'store'])->name('swaps.store');
            Route::post('/swaps/{swap}/respond', [\App\Http\Controllers\Tenant\Workspace\WorkspaceSwapController::class, 'respond'])->name('swaps.respond');
            Route::post('/swaps/{swap}/decide', [\App\Http\Controllers\Tenant\Workspace\WorkspaceSwapController::class, 'lecturerDecide'])->name('swaps.decide');
        });

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

        // Student Assessments (submission)
        Route::get('/my-assessments', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'studentIndex'])->name('tenant.my-assessments');
        Route::get('/courses/{course}/assessments/{assessment}/view', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'studentShow'])->name('tenant.my-assessments.show');
        Route::post('/courses/{course}/assessments/{assessment}/submit', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'studentSubmit'])->name('tenant.my-assessments.submit');
        Route::delete('/courses/{course}/assessments/{assessment}/submission', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'studentDeleteSubmission'])->name('tenant.my-assessments.delete');
        Route::post('/courses/{course}/assessments/{assessment}/resubmit', [\App\Http\Controllers\Tenant\Assessment\AssessmentSubmissionController::class, 'studentResubmit'])->name('tenant.my-assessments.resubmit');

        // Academic Terms (Semesters)
        Route::get('/semesters', [\App\Http\Controllers\Tenant\AcademicTermController::class, 'index'])->name('tenant.academic-terms.index');
        Route::post('/semesters', [\App\Http\Controllers\Tenant\AcademicTermController::class, 'store'])->name('tenant.academic-terms.store');
        Route::put('/semesters/{term}', [\App\Http\Controllers\Tenant\AcademicTermController::class, 'update'])->name('tenant.academic-terms.update');
        Route::delete('/semesters/{term}', [\App\Http\Controllers\Tenant\AcademicTermController::class, 'destroy'])->name('tenant.academic-terms.destroy');

        // Settings
        Route::get('/settings', [\App\Http\Controllers\Tenant\SettingsController::class, 'index'])->name('tenant.settings');
        Route::get('/settings/drive/connect', [\App\Http\Controllers\Tenant\SettingsController::class, 'connectDrive'])->name('tenant.settings.drive.connect');
        Route::post('/settings/drive/folder', [\App\Http\Controllers\Tenant\SettingsController::class, 'updateDriveFolder'])->name('tenant.settings.drive.folder');
        Route::post('/settings/drive/folder/reset', [\App\Http\Controllers\Tenant\SettingsController::class, 'resetDriveFolder'])->name('tenant.settings.drive.folder.reset');
        Route::post('/settings/drive/disconnect', [\App\Http\Controllers\Tenant\SettingsController::class, 'disconnectDrive'])->name('tenant.settings.drive.disconnect');
    });

require __DIR__.'/auth.php';
