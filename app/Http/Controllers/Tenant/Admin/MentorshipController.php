<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\StudentMentorship;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MentorshipController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin();
        $tenant = app('current_tenant');

        $role = $request->input('role');

        $mentorships = StudentMentorship::query()
            ->with(['lecturer', 'student', 'academicTerm'])
            ->when($role, fn ($q) => $q->where('role', $role))
            ->latest('assigned_at')
            ->get();

        return view('tenant.admin.mentorships.index', compact('tenant', 'mentorships', 'role'));
    }

    public function create(): View
    {
        $this->authorizeAdmin();
        $tenant = app('current_tenant');

        $lecturers = User::whereHas('tenantUsers', fn ($q) => $q
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['lecturer', 'coordinator'])
            ->where('is_active', true)
        )->orderBy('name')->get();

        $students = User::whereHas('tenantUsers', fn ($q) => $q
            ->where('tenant_id', $tenant->id)
            ->where('role', 'student')
            ->where('is_active', true)
        )->orderBy('name')->get();

        $terms = AcademicTerm::orderByDesc('start_date')->get();

        return view('tenant.admin.mentorships.create', compact('tenant', 'lecturers', 'students', 'terms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $tenant = app('current_tenant');

        $validated = $request->validate([
            'lecturer_id' => ['required', 'exists:users,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['exists:users,id'],
            'role' => ['required', 'in:academic_tutor,li_supervisor'],
            'academic_term_id' => ['nullable', 'exists:academic_terms,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $created = 0;
        foreach ($validated['student_ids'] as $studentId) {
            $mentorship = StudentMentorship::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'lecturer_id' => $validated['lecturer_id'],
                    'student_id' => $studentId,
                    'role' => $validated['role'],
                    'academic_term_id' => $validated['academic_term_id'] ?? null,
                ],
                [
                    'assigned_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            if ($mentorship->wasRecentlyCreated) {
                $created++;
            }
        }

        return redirect()
            ->route('tenant.admin.mentorships.index', $tenant->slug)
            ->with('success', "{$created} mentorship(s) assigned.");
    }

    public function destroy(string $tenantSlug, StudentMentorship $mentorship): RedirectResponse
    {
        $this->authorizeAdmin();

        $mentorship->update(['ended_at' => now()]);
        $mentorship->delete();

        return back()->with('success', 'Mentorship revoked.');
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        if ($user->is_super_admin) {
            return;
        }

        if (! $user->hasRoleInTenant($tenant->id, ['admin', 'coordinator'])) {
            abort(403);
        }
    }
}
