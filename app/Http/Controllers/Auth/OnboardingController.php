<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Course;
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
            return redirect('/'.$tenantUser->tenant->slug.'/dashboard');
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
            $baseSlug = $this->generateSlug($request->new_tenant_name);

            // Ensure unique slug
            $slug = $baseSlug;
            $i = 2;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug.$i++;
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

            return redirect('/'.$tenant->slug.'/dashboard')->with('success', 'Welcome! '.$tenant->name.' has been created. You are the first '.ucfirst($request->role).'.');
        }

        // Join existing institution
        $tenant = Tenant::findOrFail($request->tenant_id);

        if ($user->tenantUsers()->where('tenant_id', $tenant->id)->exists()) {
            return redirect('/'.$tenant->slug.'/dashboard');
        }

        // Students with invite code: also enroll in section
        if ($request->role === 'student' && $request->filled('invite_code')) {
            $raw = (string) $request->invite_code;
            $code = preg_replace('/[^A-Z0-9]/', '', strtoupper($raw));

            $section = Section::where('invite_code', $code)
                ->whereHas('course', fn ($q) => $q->where('tenant_id', $tenant->id))
                ->first();

            if (! $section) {
                $isCourseCode = $code !== '' && Course::where('invite_code', $code)
                    ->where('tenant_id', $tenant->id)
                    ->exists();

                \Log::info('Onboarding enroll: invite code miss', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'raw' => $raw,
                    'raw_bytes' => bin2hex($raw),
                    'normalized' => $code,
                    'is_course_code' => $isCourseCode,
                ]);

                $message = $isCourseCode
                    ? 'That code belongs to a course, not a section. Please ask your lecturer for the section invite code (shown as "Student code" next to each section).'
                    : 'Invalid invite code for this institution.';

                return back()->withErrors(['invite_code' => $message])->withInput();
            }

            if (! $section->is_active) {
                return back()->withErrors(['invite_code' => 'This section is not accepting enrollments. Please contact your lecturer.'])->withInput();
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

            return redirect('/'.$tenant->slug.'/dashboard')->with('success', 'Welcome! You joined '.$tenant->name.' and enrolled in '.$section->name.'.');
        }

        // Lecturers with invite code: also claim the course
        if ($request->role === 'lecturer' && $request->filled('invite_code')) {
            $code = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $request->invite_code));

            $course = Course::where('invite_code', $code)
                ->where('tenant_id', $tenant->id)
                ->first();

            if (! $course) {
                return back()->withErrors(['invite_code' => 'Invalid course invite code for this institution.'])->withInput();
            }

            TenantUser::create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'role' => 'lecturer',
                'is_active' => true,
                'joined_at' => now(),
            ]);

            $course->update(['lecturer_id' => $user->id]);

            return redirect('/'.$tenant->slug.'/dashboard')->with('success', 'Welcome! You joined '.$tenant->name.' and claimed course '.$course->code.' — '.$course->title.'.');
        }

        // Join with selected role
        TenantUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => $request->role,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return redirect('/'.$tenant->slug.'/dashboard')->with('success', 'Welcome! You joined '.$tenant->name.' as '.ucfirst($request->role).'.');
    }

    private function generateSlug(string $name): string
    {
        $stopWords = ['of', 'the', 'and', 'at', 'in', 'for', 'a', 'an', 'de', 'la', 'le', 'di'];

        // Split on whitespace/hyphens, drop empty parts
        $words = array_values(array_filter(preg_split('/[\s\-]+/', $name)));

        // If already a single short word, just slugify it
        if (count($words) === 1) {
            return strtolower(substr(preg_replace('/[^a-z0-9]/', '', Str::slug($words[0])), 0, 12));
        }

        // Build acronym from significant words (skip stop words)
        $significant = array_filter($words, fn ($w) => ! in_array(strtolower($w), $stopWords));
        $acronym = strtolower(implode('', array_map(fn ($w) => preg_replace('/[^a-z0-9]/i', '', $w[0]), $significant)));

        // If acronym is meaningful (2+ chars), use it; otherwise fall back to truncated slug
        if (strlen($acronym) >= 2) {
            return $acronym;
        }

        return substr(Str::slug($name), 0, 12);
    }
}
