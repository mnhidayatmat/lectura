<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AttendanceExcuse;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\SectionStudent;
use App\Services\Attendance\AttendanceExcuseService;
use App\Services\Attendance\AttendanceWarningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentAttendanceController extends Controller
{
    public function __construct(
        protected AttendanceWarningService $warningService,
        protected AttendanceExcuseService $excuseService,
    ) {}

    /**
     * List all enrolled courses with attendance summaries.
     */
    public function index(): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        $enrollments = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['section.course.lecturer', 'section.course.attendancePolicy'])
            ->get();

        $courses = $enrollments->groupBy(fn ($e) => $e->section->course_id)->map(function ($group) use ($user) {
            $course = $group->first()->section->course;
            $summary = $this->warningService->getAbsenceSummary($course, $user);

            return (object) [
                'course' => $course,
                'sections' => $group->pluck('section'),
                'summary' => $summary,
            ];
        })->values();

        return view('tenant.attendance.student.index', compact('tenant', 'courses'));
    }

    /**
     * Detailed attendance for a specific course.
     */
    public function course(string $tenantSlug, Course $course): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        $mySectionIds = SectionStudent::whereHas('section', fn ($q) => $q->where('course_id', $course->id))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('section_id');

        if ($mySectionIds->isEmpty()) {
            abort(403, 'You are not enrolled in this course.');
        }

        $summary = $this->warningService->getAbsenceSummary($course, $user);

        // Get all sessions with this student's records
        $sessions = AttendanceSession::whereIn('section_id', $mySectionIds)
            ->where('status', 'ended')
            ->with(['section'])
            ->orderByDesc('started_at')
            ->get();

        $sessionIds = $sessions->pluck('id');

        $records = AttendanceRecord::where('user_id', $user->id)
            ->whereIn('attendance_session_id', $sessionIds)
            ->with('excuse')
            ->get()
            ->keyBy('attendance_session_id');

        $policy = $course->attendancePolicy;

        return view('tenant.attendance.student.course', compact(
            'tenant', 'course', 'summary', 'sessions', 'records', 'policy'
        ));
    }

    /**
     * Submit an excuse for an absent record.
     */
    public function submitExcuse(Request $request, string $tenantSlug, AttendanceRecord $record): RedirectResponse
    {
        $user = auth()->user();

        if ($record->user_id !== $user->id) {
            abort(403);
        }

        if ($record->status !== 'absent') {
            return back()->with('error', 'You can only submit excuses for absent records.');
        }

        if ($record->excuse) {
            return back()->with('error', 'An excuse has already been submitted for this session.');
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'in:medical,family_emergency,academic_conflict,official_duty,other'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $this->excuseService->submit(
            $record,
            $user,
            $request->only(['reason', 'category']),
            $request->file('attachment'),
        );

        return back()->with('success', 'Your excuse has been submitted for review.');
    }
}
