<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\AttendanceExcuse;
use App\Models\Course;
use App\Services\Attendance\AttendanceExcuseService;
use App\Services\Attendance\AttendanceWarningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceExcuseController extends Controller
{
    use AuthorizesCourseAccess;
    public function __construct(
        protected AttendanceExcuseService $excuseService,
        protected AttendanceWarningService $warningService,
    ) {}

    /**
     * List excuses across lecturer's courses.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        $courseIds = $this->accessibleCourseIds();

        $status = $request->query('status', 'pending');

        $excuses = AttendanceExcuse::whereHas('record.session.section', function ($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds);
            })
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with([
                'user',
                'reviewer',
                'record.session.section.course',
            ])
            ->latest()
            ->paginate(20);

        $pendingCount = AttendanceExcuse::whereHas('record.session.section', function ($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds);
            })
            ->pending()
            ->count();

        return view('tenant.attendance.excuses.index', compact('excuses', 'status', 'pendingCount'));
    }

    /**
     * Approve an excuse.
     */
    public function approve(Request $request, string $tenantSlug, AttendanceExcuse $excuse): RedirectResponse
    {
        $this->authorizeExcuse($excuse);

        if ($error = $this->semesterClosedError($excuse)) {
            return back()->with('error', $error);
        }

        $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->excuseService->approve($excuse, auth()->user(), $request->note);

        // Recalculate warnings (absence decreased)
        $course = $excuse->record->session->section->course;
        $this->warningService->checkAndIssueWarnings($course);

        return back()->with('success', 'Excuse approved. Student record updated to excused.');
    }

    /**
     * Reject an excuse.
     */
    public function reject(Request $request, string $tenantSlug, AttendanceExcuse $excuse): RedirectResponse
    {
        $this->authorizeExcuse($excuse);

        if ($error = $this->semesterClosedError($excuse)) {
            return back()->with('error', $error);
        }

        $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->excuseService->reject($excuse, auth()->user(), $request->note);

        return back()->with('success', 'Excuse rejected.');
    }

    /**
     * Download excuse attachment.
     */
    public function downloadAttachment(string $tenantSlug, AttendanceExcuse $excuse)
    {
        $this->authorizeExcuse($excuse);

        if (! $excuse->attachment_path) {
            abort(404);
        }

        return \Illuminate\Support\Facades\Storage::disk('uploads')
            ->download($excuse->attachment_path, $excuse->attachment_filename);
    }

    /**
     * Pending excuses stay reviewable after the edit window passes, so one submitted
     * near its end can still be decided; only closing the semester (or archiving
     * the course) stops review.
     */
    protected function semesterClosedError(AttendanceExcuse $excuse): ?string
    {
        $session = $excuse->record->session;

        return in_array($session->lockReason(), ['semester_closed', 'course_archived'], true) ? $session->lockMessage() : null;
    }

    protected function authorizeExcuse(AttendanceExcuse $excuse): void
    {
        $courseOwnerId = $excuse->record->session->section->course->lecturer_id;

        if ($courseOwnerId !== auth()->id() && ! auth()->user()->is_super_admin) {
            abort(403);
        }
    }
}
