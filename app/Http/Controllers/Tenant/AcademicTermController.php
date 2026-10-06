<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\AttendanceSession;
use App\Models\Section;
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

        $terms = AcademicTerm::withCount(['courses', 'sections'])
            ->orderByDesc('start_date')
            ->get();

        // Courses are offered in a semester through their sections
        $terms->each(function (AcademicTerm $term) {
            $term->setAttribute('offered_sections_count', Section::inTerm($term)->count());
            $term->setAttribute('offered_courses_count', Section::inTerm($term)->distinct()->count('course_id'));
        });

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
     * Closing a semester locks the attendance of every section running in it
     * (AttendanceSession::lockReason). Courses are not touched: a course lives on
     * across semesters, and only its sections belong to one. Any attendance
     * session still running is ended first, so a forgotten QR code stops
     * accepting scans and its no-shows are recorded.
     */
    public function close(string $tenantSlug, AcademicTerm $term, AttendanceSessionService $sessionService): RedirectResponse
    {
        $this->authorizeStaff();
        $this->authorizeTerm($term);

        $tenant = app('current_tenant');

        if ($term->isClosed()) {
            return redirect()->route('tenant.academic-terms.index', $tenant->slug)
                ->with('error', 'That semester is already closed.');
        }

        $running = AttendanceSession::where('status', 'active')
            ->whereHas('section', fn ($q) => $q->inTerm($term))
            ->get();

        foreach ($running as $session) {
            $sessionService->end($session);
        }

        $term->update(['closed_at' => now()]);

        $message = "Closed {$term->name}. Its attendance is now locked.";

        if ($running->isNotEmpty()) {
            $message .= " Ended {$running->count()} running attendance ".Str::plural('session', $running->count()).'.';
        }

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', $message);
    }

    /**
     * The reverse of close, so closing a semester by mistake is one click to undo.
     */
    public function reopen(string $tenantSlug, AcademicTerm $term): RedirectResponse
    {
        $this->authorizeStaff();
        $this->authorizeTerm($term);

        $tenant = app('current_tenant');

        if (! $term->isClosed()) {
            return redirect()->route('tenant.academic-terms.index', $tenant->slug)
                ->with('error', 'That semester is not closed.');
        }

        $term->update(['closed_at' => null]);

        return redirect()->route('tenant.academic-terms.index', $tenant->slug)
            ->with('success', "Reopened {$term->name}. Its attendance can be changed again.");
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
