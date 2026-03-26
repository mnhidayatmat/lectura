<?php

use App\Http\Controllers\ProfileController;
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

            return view('tenant.dashboard', compact('tenant', 'role'));
        })->name('tenant.dashboard');

        // Course Management
        Route::get('/courses', function () {
            return view('tenant.courses.index');
        })->name('tenant.courses.index');

        // Attendance
        Route::get('/attendance', function () {
            return view('tenant.placeholder', ['title' => __('nav.attendance'), 'description' => 'QR attendance management will be available here.']);
        })->name('tenant.attendance.index');

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
            return view('tenant.placeholder', ['title' => __('nav.scan'), 'description' => 'QR attendance scanning will be available here.']);
        })->name('tenant.scan');

        Route::get('/my-courses', function () {
            return view('tenant.placeholder', ['title' => __('nav.courses'), 'description' => 'Your enrolled courses will appear here.']);
        })->name('tenant.my-courses');

        Route::get('/marks', function () {
            return view('tenant.placeholder', ['title' => __('nav.marks'), 'description' => 'Your marks and feedback will be displayed here.']);
        })->name('tenant.marks');
    });

require __DIR__.'/auth.php';
