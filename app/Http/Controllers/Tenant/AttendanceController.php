<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Services\Attendance\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        protected QrCodeService $qrService,
    ) {}

    /**
     * Attendance overview — list sessions across lecturer's courses.
     */
    public function index(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $courseIds = Course::where('lecturer_id', $user->id)->pluck('id');
        $sectionIds = Section::whereIn('course_id', $courseIds)->pluck('id');

        $sessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->with(['section.course', 'records'])
            ->latest('started_at')
            ->limit(50)
            ->get();

        // Group active sessions
        $activeSessions = $sessions->where('status', 'active');
        $pastSessions = $sessions->where('status', 'ended');

        // Get lecturer's sections for starting new session
        $sections = Section::whereIn('course_id', $courseIds)
            ->with('course')
            ->where('is_active', true)
            ->get();

        return view('tenant.attendance.index', compact('activeSessions', 'pastSessions', 'sections'));
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
        $section = Section::findOrFail($request->section_id);

        // Verify section belongs to lecturer's course
        $course = $section->course;
        if ($course->lecturer_id !== auth()->id()) {
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
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

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
        if (! auth()->user()->is_super_admin && $session->lecturer_id !== auth()->id()) {
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

        // Validate token
        if ($session->qr_mode === 'rotating') {
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
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

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

        return redirect()->route('tenant.attendance.index', app('current_tenant')->slug)
            ->with('success', 'Session ended. ' . $enrolledStudents->count() . ' students marked absent.');
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
     * View session details/report.
     */
    public function show(string $tenantSlug, AttendanceSession $session): View
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $session->load(['section.course', 'records.user']);

        return view('tenant.attendance.show', compact('session'));
    }

    /**
     * Delete an attendance session and all its records.
     */
    public function destroy(string $tenantSlug, AttendanceSession $session): RedirectResponse
    {
        if ($session->lecturer_id !== auth()->id()) {
            abort(403);
        }

        // Don't allow deleting active sessions — end them first
        if ($session->status === 'active') {
            return back()->with('error', 'Cannot delete an active session. End it first.');
        }

        $session->records()->delete();
        $session->delete();

        return redirect()->route('tenant.attendance.index', $tenantSlug)
            ->with('success', 'Attendance session deleted.');
    }
}
