<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\AttendanceSession;
use App\Services\Attendance\AttendanceSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AcademicTermController extends Controller
{
    /**
     * Semesters drive courses and sections, so only institution staff may manage them.
     */
    protected function authorizeStaff(): void
    {
        if (! auth()->user()->hasRoleInTenant(app('current_tenant')->id, ['admin', 'coordinator'])) {
            abort(403);
        }
    }

    /**
     * Web route-model binding runs before ResolveTenant binds `current_tenant`, and
     * BelongsToTenant's global scope no-ops while nothing is bound — so a semester
     * belonging to another institution does reach these methods. The mobile API
     * avoids this by ordering ResolveApiTenant ahead of SubstituteBindings in
     * bootstrap/app.php; the web stack has no such ordering, so check it here.
     */
    protected function authorizeTerm(AcademicTerm $term): void
    {
        if ($term->tenant_id !== app('current_tenant')->id) {
            abort(404);
        }
    }

    public function index(): View
    {
        $this->authorizeStaff();

        $terms = AcademicTerm::withCount([
            'courses',
            'sections',
            'courses as archived_courses_count' => fn ($query) => $query->where('status', 'archived'),
        ])
            ->orderByDesc('start_date')
            ->get();

        return view('tenant.academic-terms.index', compact('terms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStaff();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $tenant = app('current_tenant');

        // If setting as default, unset others
        if ($request->boolean('is_default')) {
            AcademicTerm::where('is_default', true)->update(['is_default' => false]);
        }

        AcademicTerm::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'code' => $request->code,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', 'Semester created successfully.');
    }

    public function update(Request $request, string $tenantSlug, AcademicTerm $term): RedirectResponse
    {
        $this->authorizeStaff();
        $this->authorizeTerm($term);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $tenant = app('current_tenant');

        if ($request->boolean('is_default') && ! $term->is_default) {
            AcademicTerm::where('is_default', true)->update(['is_default' => false]);
        }

        $term->update([
            'name' => $request->name,
            'code' => $request->code,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', 'Semester updated successfully.');
    }

    /**
     * Closing a semester archives its courses, which is what "the session is over"
     * means in practice: they drop out of every lecturer's current list but keep
     * all their records and stay reachable under Archived.
     *
     * Sections, assessments and enrolments are deliberately left alone. Attendance
     * is the exception: an archived course locks its sessions (AttendanceSession::
     * lockReason), and any session still running is ended first so a forgotten QR
     * code stops accepting scans and its no-shows are recorded.
     */
    public function archiveCourses(string $tenantSlug, AcademicTerm $term, AttendanceSessionService $sessionService): RedirectResponse
    {
        $this->authorizeStaff();
        $this->authorizeTerm($term);

        $tenant = app('current_tenant');

        $openCourseIds = $term->courses()->where('status', '!=', 'archived')->pluck('id');

        if ($openCourseIds->isEmpty()) {
            return redirect()->route('tenant.academic-terms.index', $tenant->slug)
                ->with('error', 'That semester has no open courses to close.');
        }

        $running = AttendanceSession::where('status', 'active')
            ->whereHas('section', fn ($q) => $q->whereIn('course_id', $openCourseIds))
            ->get();

        foreach ($running as $session) {
            $sessionService->end($session);
        }

        $archived = $term->courses()->whereIn('id', $openCourseIds)->update(['status' => 'archived']);

        $message = "Closed the semester and archived {$archived} ".Str::plural('course', $archived).'.';

        if ($running->isNotEmpty()) {
            $message .= " Ended {$running->count()} running attendance ".Str::plural('session', $running->count()).'.';
        }

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', $message);
    }

    /**
     * The reverse of archiveCourses, so closing a semester by mistake is one click
     * to undo rather than an edit per course.
     */
    public function reopenCourses(string $tenantSlug, AcademicTerm $term): RedirectResponse
    {
        $this->authorizeStaff();
        $this->authorizeTerm($term);

        $tenant = app('current_tenant');

        $reopened = $term->courses()->where('status', 'archived')->update(['status' => 'active']);

        if ($reopened === 0) {
            return redirect()->route('tenant.academic-terms.index', $tenant->slug)
                ->with('error', 'That semester has no archived courses to reopen.');
        }

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', "Reopened the semester and restored {$reopened} ".Str::plural('course', $reopened).'.');
    }

    public function destroy(string $tenantSlug, AcademicTerm $term): RedirectResponse
    {
        $this->authorizeStaff();
        $this->authorizeTerm($term);

        $tenant = app('current_tenant');

        if ($term->courses()->exists() || $term->sections()->exists()) {
            return redirect()->route('tenant.academic-terms.index', $tenant->slug)
                ->with('error', 'Cannot delete a semester that has courses or sections assigned to it.');
        }

        $term->delete();

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', 'Semester deleted successfully.');
    }
}
