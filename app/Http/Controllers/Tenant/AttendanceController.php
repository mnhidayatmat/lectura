<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Services\Attendance\AttendanceWarningService;
use App\Services\Attendance\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        protected QrCodeService $qrService,
        protected AttendanceWarningService $warningService,
    ) {}

    /**
     * Attendance overview — pick a course to manage its sessions.
     */
    public function index(): View
    {
        $sectionIds = $this->allAccessibleSectionIds();

        $sections = Section::whereIn('id', $sectionIds)->get(['id', 'course_id', 'is_active']);

        $sessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->with('section:id,course_id,name')
            ->withCount([
                'records',
                'records as attended_count' => fn ($q) => $q->whereIn('status', ['present', 'late']),
            ])
            ->get();

        $activeSessions = $sessions->where('status', 'active')
            ->load('section.course')
            ->sortByDesc('started_at');

        $sessionsByCourse = $sessions->groupBy(fn ($session) => $session->section->course_id);

        $courses = Course::whereIn('id', $sections->pluck('course_id')->unique())
            ->with('academicTerm')
            ->orderBy('code')
            ->get()
            ->map(function (Course $course) use ($sections, $sessionsByCourse) {
                $courseSessions = $sessionsByCourse->get($course->id, collect());
                $ended = $courseSessions->where('status', 'ended');
                $totalRecords = $ended->sum('records_count');

                $course->attendance_stats = [
                    'sections' => $sections->where('course_id', $course->id)->where('is_active', true)->count(),
                    'sessions' => $courseSessions->count(),
                    'live' => $courseSessions->where('status', 'active')->count(),
                    'rate' => $totalRecords > 0 ? (int) round($ended->sum('attended_count') / $totalRecords * 100) : null,
                    'last' => $courseSessions->max('started_at'),
                ];

                return $course;
            });

        [$archivedCourses, $currentCourses] = $courses->partition(fn (Course $course) => $course->status === 'archived');

        return view('tenant.attendance.index', compact('activeSessions', 'currentCourses', 'archivedCourses'));
    }

    /**
     * Sessions, reports and session start for a single course.
     */
    public function course(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        $sectionIds = $this->allAccessibleSectionIds();

        $sections = Section::where('course_id', $course->id)
            ->whereIn('id', $sectionIds)
            ->orderBy('name')
            ->get();

        $sessions = AttendanceSession::whereIn('section_id', $sections->pluck('id'))
            ->with('section')
            ->withCount([
                'records',
                'records as present_count' => fn ($q) => $q->where('status', 'present'),
                'records as late_count' => fn ($q) => $q->where('status', 'late'),
                'records as absent_count' => fn ($q) => $q->where('status', 'absent'),
                'records as excused_count' => fn ($q) => $q->where('status', 'excused'),
            ])
            ->latest('started_at')
            ->get();

        $activeSessions = $sessions->where('status', 'active');
        $pastSessions = $sessions->where('status', 'ended')->values();

        $totalRecords = $pastSessions->sum('records_count');
        $stats = [
            'sessions' => $pastSessions->count(),
            'rate' => $totalRecords > 0
                ? (int) round(($pastSessions->sum('present_count') + $pastSessions->sum('late_count')) / $totalRecords * 100)
                : null,
            'students' => SectionStudent::whereIn('section_id', $sections->pluck('id'))->where('is_active', true)->count(),
            'last' => $sessions->max('started_at'),
        ];

        $activeSections = $sections->where('is_active', true)->values();

        return view('tenant.attendance.course', compact('course', 'sections', 'activeSections', 'activeSessions', 'pastSessions', 'stats'));
    }

    /**
     * Start a new attendance session.
     */
    public function start(Request $request): RedirectResponse
    {
        $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'session_type' => ['required', 'in:lecture,tutorial,lab,extra,replacement'],
            'week_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenant = app('current_tenant');
        $user = auth()->user();
        $section = Section::findOrFail($request->section_id);
        $course = $section->course;

        // Verify user owns this section (as section lecturer or course owner or admin)
        $canAccess = $section->lecturers->contains('id', $user->id)
            || $course->lecturer_id === $user->id
            || $user->hasRoleInTenant($tenant->id, ['admin']);

        if (! $canAccess) {
            abort(403);
        }

        // Check no active session for this section
        $existing = AttendanceSession::where('section_id', $section->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return redirect()->route('tenant.attendance.qr', [
                'tenant' => $tenant->slug,
                'session' => $existing->id,
            ])->with('error', 'An active session already exists for this section.');
        }

        $session = AttendanceSession::create([
            'tenant_id' => $tenant->id,
            'section_id' => $section->id,
            'lecturer_id' => auth()->id(),
            'session_type' => $request->session_type,
            'week_number' => $request->week_number,
            'qr_secret' => Str::random(64),
            'qr_mode' => 'rotating',
            'qr_rotation_seconds' => config('lectura.attendance.qr_rotation_seconds', 30),
            'late_threshold_minutes' => config('lectura.attendance.late_threshold_minutes', 15),
            'status' => 'active',
            'started_at' => now(),
        ]);

        return redirect()->route('tenant.attendance.qr', [
            'tenant' => $tenant->slug,
            'session' => $session->id,
        ]);
    }

    /**
     * QR display page for lecturer — shows rotating QR code.
     */
    public function qr(string $tenantSlug, AttendanceSession $session): View
    {
        $this->authorizeSession($session);

        $session->load(['section.course', 'section.activeStudents', 'records.user']);

        $totalStudents = $session->section->activeStudents->count();
        $checkedIn = $session->records->whereIn('status', ['present', 'late'])->count();

        return view('tenant.attendance.qr', compact('session', 'totalStudents', 'checkedIn'));
    }

    /**
     * API: Generate fresh QR token (called by JS on lecturer's page).
     */
    public function refreshToken(string $tenantSlug, AttendanceSession $session): JsonResponse
    {
        try {
            $this->authorizeSession($session);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        if (! $session->isActive()) {
            return response()->json(['error' => 'Session ended'], 403);
        }

        $token = $this->qrService->generateToken(
            $session->qr_secret,
            $session->qr_rotation_seconds
        );

        $payload = $this->qrService->buildPayload($session->id, $token);

        $records = $session->records()
            ->whereIn('status', ['present', 'late'])
            ->with('user:id,name')
            ->orderByDesc('checked_in_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->user->name,
                'status' => $r->status,
                'time' => $r->checked_in_at?->format('H:i:s'),
            ]);

        return response()->json([
            'payload' => $payload,
            'rotation_seconds' => $session->qr_rotation_seconds,
            'checked_in' => $records->count(),
            'total' => $session->section->activeStudents()->count(),
            'records' => $records,
        ]);
    }

    /**
     * API: Student check-in via QR scan.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'payload' => ['required', 'string'],
        ]);

        $parsed = $this->qrService->parsePayload($request->payload);

        if (! $parsed) {
            return response()->json(['error' => 'Invalid QR code.'], 422);
        }

        $session = AttendanceSession::find($parsed['session_id']);

        if (! $session || ! $session->isActive()) {
            return response()->json(['error' => 'This attendance session has ended.'], 422);
        }

        // Validate token — verify unless the session is explicitly fixed-code.
        // `qr_mode` is a plain string column, so testing for 'rotating' let any
        // other value through unverified.
        if ($session->qr_mode !== 'fixed') {
            $valid = $this->qrService->validateToken(
                $parsed['token'],
                $session->qr_secret,
                $session->qr_rotation_seconds
            );

            if (! $valid) {
                return response()->json(['error' => 'QR code has expired. Please scan the latest code.'], 422);
            }
        }

        $user = auth()->user();

        // Check student is enrolled in this section
        $enrolled = SectionStudent::where('section_id', $session->section_id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $enrolled) {
            return response()->json(['error' => 'You are not enrolled in this section.'], 422);
        }

        // Check duplicate
        $existing = AttendanceRecord::where('attendance_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You have already checked in.',
                'status' => $existing->status,
                'checked_in_at' => $existing->checked_in_at->format('H:i:s'),
            ]);
        }

        // Determine if late
        $minutesSinceStart = $session->started_at->diffInMinutes(now());
        $status = $minutesSinceStart > $session->late_threshold_minutes ? 'late' : 'present';

        $record = AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'user_id' => $user->id,
            'status' => $status,
            'checked_in_at' => now(),
            'method' => 'qr_scan',
            'device_info' => [
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 200),
            ],
        ]);

        return response()->json([
            'message' => $status === 'late' ? 'Checked in (late).' : 'Checked in successfully!',
            'status' => $record->status,
            'checked_in_at' => $record->checked_in_at->format('H:i:s'),
        ]);
    }

    /**
     * End an active session.
     */
    public function end(string $tenantSlug, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeSession($session);

        $session->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        // Mark absent students
        $checkedInUserIds = $session->records()->pluck('user_id');
        $enrolledStudents = SectionStudent::where('section_id', $session->section_id)
            ->where('is_active', true)
            ->whereNotIn('user_id', $checkedInUserIds)
            ->pluck('user_id');

        foreach ($enrolledStudents as $userId) {
            AttendanceRecord::create([
                'attendance_session_id' => $session->id,
                'user_id' => $userId,
                'status' => 'absent',
                'method' => 'manual',
            ]);
        }

        // Check and issue attendance warnings
        $this->warningService->checkAndIssueWarnings($session->section->course);

        return redirect()->route('tenant.attendance.course', [app('current_tenant')->slug, $session->section->course_id])
            ->with('success', 'Session ended. ' . $enrolledStudents->count() . ' students marked absent.');
    }

    /**
     * Reopen an ended session so late students can check in again.
     */
    public function reopen(string $tenantSlug, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeSession($session);

        if ($session->status !== 'ended') {
            return back()->with('error', 'Only ended sessions can be reopened.');
        }

        // Remove auto-generated absent records (so late students can re-scan).
        // Keep records that were manually overridden by the lecturer (override_by IS NOT NULL)
        // or that have a checked_in_at (i.e. the student actually scanned).
        $session->records()
            ->where('status', 'absent')
            ->where('method', 'manual')
            ->whereNull('checked_in_at')
            ->whereNull('override_by')
            ->delete();

        $session->update([
            'status' => 'active',
            'ended_at' => null,
        ]);

        return redirect()->route('tenant.attendance.qr', [$tenantSlug, $session])
            ->with('success', 'Session reopened. Students can now scan again.');
    }

    /**
     * Manual override — update student status.
     */
    public function override(Request $request, string $tenantSlug, AttendanceSession $session, AttendanceRecord $record): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'in:present,late,absent,excused'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $record->update([
            'status' => $request->status,
            'override_by' => auth()->id(),
            'override_reason' => $request->reason,
        ]);

        return back()->with('success', 'Attendance updated.');
    }

    /**
     * Update session metadata (type / week number). Useful to correct a wrong
     * session_type picked when starting the session (e.g. lecture vs lab).
     */
    public function update(Request $request, string $tenantSlug, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeSession($session);

        $validated = $request->validate([
            'session_type' => ['required', 'in:lecture,tutorial,lab,extra,replacement'],
            'week_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $session->update($validated);

        return back()->with('success', 'Session details updated.');
    }

    /**
     * View session details/report.
     */
    public function show(string $tenantSlug, AttendanceSession $session): View
    {
        $this->authorizeSession($session);

        $session->load(['section.course', 'records.user']);

        return view('tenant.attendance.show', compact('session'));
    }

    /**
     * Delete an attendance session and all its records.
     */
    public function destroy(string $tenantSlug, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeSession($session);

        // Don't allow deleting active sessions — end them first
        if ($session->status === 'active') {
            return back()->with('error', 'Cannot delete an active session. End it first.');
        }

        $courseId = $session->section->course_id;

        $session->records()->delete();
        $session->delete();

        return redirect()->route('tenant.attendance.course', [$tenantSlug, $courseId])
            ->with('success', 'Attendance session deleted.');
    }

    /**
     * Authorize that the current user can access this attendance session.
     * Allows: session creator, section lecturers, course owner, tenant admins.
     */
    protected function authorizeSession(AttendanceSession $session): void
    {
        $user = auth()->user();

        if ($session->lecturer_id === $user->id) {
            return;
        }

        $session->loadMissing('section.course');
        $section = $session->section;
        $course = $section?->course;

        $canAccess = ($section && $section->lecturers->contains('id', $user->id))
            || ($course && $course->lecturer_id === $user->id)
            || $user->hasRoleInTenant(app('current_tenant')->id, ['admin']);

        if (! $canAccess) {
            abort(403);
        }
    }
}
