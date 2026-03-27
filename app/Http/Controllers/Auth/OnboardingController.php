<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $user = auth()->user();

        // If user already has an active tenant, skip onboarding
        $tenantUser = $user->tenantUsers()->where('is_active', true)->first();
        if ($tenantUser) {
            return redirect('/' . $tenantUser->tenant->slug . '/dashboard');
        }

        $tenants = Tenant::where('is_active', true)->orderBy('name')->get();

        return view('auth.onboarding', compact('tenants'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'role' => ['required', 'in:lecturer,student'],
            'invite_code' => ['nullable', 'string'],
        ]);

        $user = auth()->user();
        $tenant = Tenant::findOrFail($request->tenant_id);

        // Check if already a member
        if ($user->tenantUsers()->where('tenant_id', $tenant->id)->exists()) {
            return redirect('/' . $tenant->slug . '/dashboard');
        }

        // For students: validate invite code if provided
        if ($request->role === 'student' && $request->filled('invite_code')) {
            $section = Section::where('invite_code', $request->invite_code)
                ->where('course_id', function ($q) use ($tenant) {
                    $q->select('id')->from('courses')->where('tenant_id', $tenant->id)->limit(1);
                })
                ->first();

            if (! $section) {
                return back()->withErrors(['invite_code' => 'Invalid invite code for this institution.'])->withInput();
            }

            // Add to tenant
            TenantUser::create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'role' => 'student',
                'is_active' => true,
                'joined_at' => now(),
            ]);

            // Enroll in section
            $section->students()->syncWithoutDetaching([
                $user->id => [
                    'enrolled_at' => now(),
                    'enrollment_method' => 'invite_code',
                    'is_active' => true,
                ],
            ]);

            return redirect('/' . $tenant->slug . '/dashboard')->with('success', 'Welcome! You have joined ' . $tenant->name . ' and enrolled in ' . $section->name . '.');
        }

        // Add to tenant with selected role
        TenantUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => $request->role,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return redirect('/' . $tenant->slug . '/dashboard')->with('success', 'Welcome! You have joined ' . $tenant->name . ' as ' . ucfirst($request->role) . '.');
    }
}
