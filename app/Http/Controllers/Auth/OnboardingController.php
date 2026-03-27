<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $user = auth()->user();

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
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'new_tenant_name' => ['nullable', 'string', 'max:255', 'min:3'],
            'role' => ['required', 'in:lecturer,student'],
            'invite_code' => ['nullable', 'string'],
        ]);

        // Must have either existing tenant or new name
        if (! $request->tenant_id && ! $request->new_tenant_name) {
            return back()->withErrors(['tenant_id' => 'Please select an institution or type a new one.'])->withInput();
        }

        $user = auth()->user();

        // Create new institution if needed
        if ($request->filled('new_tenant_name') && ! $request->tenant_id) {
            $slug = Str::slug($request->new_tenant_name);

            // Ensure unique slug
            $baseSlug = $slug;
            $i = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $i++;
            }

            $tenant = Tenant::create([
                'name' => $request->new_tenant_name,
                'slug' => $slug,
                'timezone' => 'Asia/Kuala_Lumpur',
                'locale' => 'en',
                'is_active' => true,
                'settings' => [
                    'auth' => ['allow_google_login' => true, 'sso_enabled' => false],
                    'ai' => ['enabled' => true, 'provider' => 'claude'],
                ],
            ]);

            // First member becomes admin
            TenantUser::create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'role' => $request->role,
                'is_active' => true,
                'joined_at' => now(),
            ]);

            return redirect('/' . $tenant->slug . '/dashboard')->with('success', 'Welcome! ' . $tenant->name . ' has been created. You are the first ' . ucfirst($request->role) . '.');
        }

        // Join existing institution
        $tenant = Tenant::findOrFail($request->tenant_id);

        if ($user->tenantUsers()->where('tenant_id', $tenant->id)->exists()) {
            return redirect('/' . $tenant->slug . '/dashboard');
        }

        // Students with invite code: also enroll in section
        if ($request->role === 'student' && $request->filled('invite_code')) {
            $section = Section::where('invite_code', $request->invite_code)
                ->whereHas('course', fn ($q) => $q->where('tenant_id', $tenant->id))
                ->first();

            if (! $section) {
                return back()->withErrors(['invite_code' => 'Invalid invite code for this institution.'])->withInput();
            }

            TenantUser::create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'role' => 'student',
                'is_active' => true,
                'joined_at' => now(),
            ]);

            $section->students()->syncWithoutDetaching([
                $user->id => [
                    'enrolled_at' => now(),
                    'enrollment_method' => 'invite_code',
                    'is_active' => true,
                ],
            ]);

            return redirect('/' . $tenant->slug . '/dashboard')->with('success', 'Welcome! You joined ' . $tenant->name . ' and enrolled in ' . $section->name . '.');
        }

        // Join with selected role
        TenantUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => $request->role,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return redirect('/' . $tenant->slug . '/dashboard')->with('success', 'Welcome! You joined ' . $tenant->name . ' as ' . ucfirst($request->role) . '.');
    }
}
