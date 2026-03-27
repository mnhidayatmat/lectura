<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentCourseController extends Controller
{
    /**
     * List all courses the student is enrolled in.
     */
    public function index(): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        $enrollments = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['section.course.lecturer', 'section.course.sections' => fn ($q) => $q->withCount('activeStudents')])
            ->get();

        // Group by course (student may be in multiple sections of same course)
        $courses = $enrollments->groupBy(fn ($e) => $e->section->course_id)->map(function ($group) {
            $first = $group->first();
            return (object) [
                'course' => $first->section->course,
                'sections' => $group->pluck('section'),
                'enrolled_at' => $first->enrolled_at,
            ];
        })->values();

        return view('tenant.courses.student-index', compact('tenant', 'courses'));
    }

    /**
     * Show a specific course for the student.
     */
    public function show(string $tenantSlug, Course $course): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');

        // Verify enrollment
        $enrolled = SectionStudent::whereHas('section', fn ($q) => $q->where('course_id', $course->id))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $enrolled) {
            abort(403, 'You are not enrolled in this course.');
        }

        $course->load(['lecturer', 'topics', 'learningOutcomes', 'sections']);

        // Get student's sections for this course
        $mySections = SectionStudent::whereHas('section', fn ($q) => $q->where('course_id', $course->id))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with('section')
            ->get()
            ->pluck('section');

        // Attendance summary
        $attendanceRecords = \App\Models\AttendanceRecord::where('user_id', $user->id)
            ->whereHas('session', fn ($q) => $q->whereIn('section_id', $mySections->pluck('id')))
            ->get();

        $attendanceSummary = [
            'present' => $attendanceRecords->where('status', 'present')->count(),
            'late' => $attendanceRecords->where('status', 'late')->count(),
            'absent' => $attendanceRecords->where('status', 'absent')->count(),
            'total' => $attendanceRecords->count(),
        ];

        // Upcoming assignments
        $upcomingAssignments = \App\Models\Assignment::where('course_id', $course->id)
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()))
            ->orderBy('deadline')
            ->limit(5)
            ->get();

        // Published active learning plans
        $activeLearningPlans = \App\Models\ActiveLearningPlan::where('course_id', $course->id)
            ->where('status', 'published')
            ->withCount('activities')
            ->orderByDesc('week_number')
            ->get();

        return view('tenant.courses.student-show', compact(
            'tenant', 'course', 'mySections', 'attendanceSummary',
            'upcomingAssignments', 'activeLearningPlans'
        ));
    }

    /**
     * Enroll in a course section by invite code.
     */
    public function enroll(Request $request): RedirectResponse
    {
        $request->validate([
            'invite_code' => ['required', 'string', 'max:20'],
        ]);

        $tenant = app('current_tenant');
        $user = auth()->user();
        $code = strtoupper(trim($request->invite_code));

        $section = Section::where('invite_code', $code)
            ->where('is_active', true)
            ->first();

        if (! $section) {
            return back()->withErrors(['invite_code' => 'Invalid invite code. Please check with your lecturer.']);
        }

        // Check if already enrolled
        $existing = SectionStudent::where('section_id', $section->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->is_active) {
                return back()->with('info', 'You are already enrolled in ' . $section->course->code . ' — ' . $section->name . '.');
            }
            // Reactivate
            $existing->update(['is_active' => true, 'enrolled_at' => now()]);
        } else {
            // Check capacity
            if ($section->capacity) {
                $currentCount = SectionStudent::where('section_id', $section->id)->where('is_active', true)->count();
                if ($currentCount >= $section->capacity) {
                    return back()->withErrors(['invite_code' => 'This section is full. Please contact your lecturer.']);
                }
            }

            SectionStudent::create([
                'section_id' => $section->id,
                'user_id' => $user->id,
                'enrolled_at' => now(),
                'enrollment_method' => 'invite_code',
                'is_active' => true,
            ]);
        }

        $course = $section->course;

        return redirect()->route('tenant.my-courses.show', [$tenant->slug, $course])
            ->with('success', 'Successfully enrolled in ' . $course->code . ' — ' . $section->name . '!');
    }
}
