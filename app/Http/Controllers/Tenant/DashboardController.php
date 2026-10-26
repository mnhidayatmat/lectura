<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentSubmission;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\SectionStudent;
use App\Models\StudentMark;
use App\Models\User;
use App\Services\Attendance\AttendanceWarningService;
use App\Services\Course\CourseContextService;
use App\Services\Episodes\WatchCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The tenant home. Lecturers land on the overview of their selected course;
 * with several courses and none selected they are sent to the course picker.
 */
class DashboardController extends Controller
{
    /** Attendance below this is shown in red when the course policy sets no bar threshold. */
    private const MIN_ATTENDANCE = 80;

    public function __construct(
        protected CourseContextService $courseContext,
        protected AttendanceWarningService $warningService,
    ) {}

    public function __invoke(string $tenantSlug): View|RedirectResponse
    {
        $tenant = app('current_tenant');
        $role = auth()->user()->roleInTenant($tenant->id);

        if ($role !== 'student') {
            if (app()->bound('current_course')) {
                return redirect()->route('tenant.courses.show', [$tenant->slug, app('current_course')]);
            }

            $hasCourses = $this->courseContext->accessibleCourses(auth()->user(), $tenant)
                ->contains(fn (Course $course) => $course->status !== 'archived');

            if ($hasCourses) {
                return redirect()->route('tenant.course-context.picker', $tenant->slug);
            }
        }

        if ($role === 'student') {
            return view('tenant.dashboard', [
                'tenant' => $tenant,
                'role' => $role,
                ...$this->studentData(auth()->user()),
            ]);
        }

        return view('tenant.dashboard', [
            'tenant' => $tenant,
            'role' => $role,
            'courseCount' => 0,
            'studentCount' => 0,
            'avgAttendance' => null,
            'courses' => collect(),
            'todaySchedule' => collect(),
        ]);
    }

    private function studentData(User $user): array
    {
        $enrollments = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('section.course.lecturer', 'section.course.attendancePolicy')
            ->get()
            ->filter(fn (SectionStudent $e) => $e->section?->course);

        $courses = $enrollments->groupBy(fn (SectionStudent $e) => $e->section->course_id)
            ->map(fn ($group) => $group->first()->section->course)
            ->values();

        $attendance = $courses->mapWithKeys(function (Course $course) use ($user) {
            $summary = $this->warningService->getAbsenceSummary($course, $user);
            $minimum = (float) ($course->attendancePolicy?->bar_threshold ?? self::MIN_ATTENDANCE);

            return [$course->id => [
                ...$summary,
                'low' => $summary['total_sessions'] > 0 && $summary['attendance_rate'] < $minimum,
            ]];
        });
        $tracked = $attendance->filter(fn (array $summary) => $summary['total_sessions'] > 0);

        $dueAssessments = $this->dueAssessments($user);

        return [
            'enrolledCourses' => $courses,
            'courseAttendance' => $attendance,
            'attendanceRate' => $tracked->isNotEmpty() ? round($tracked->avg('attendance_rate')) : null,
            'attendanceWarning' => $attendance->contains(fn (array $summary) => $summary['warning_level'] !== null),
            'lowAttendanceCourses' => $courses->filter(fn (Course $course) => $attendance[$course->id]['low'])->values(),
            'openCheckIns' => $this->openCheckIns($user, $enrollments->pluck('section_id')),
            'dueAssessments' => $dueAssessments,
            'pendingCount' => $dueAssessments->where('submitted', false)->count(),
            'recentMarks' => $this->recentMarks($user, $courses->pluck('id')),
            // The Watch banner must never break the dashboard.
            'watchFeatured' => rescue(fn () => app(WatchCatalog::class)->home($user)['featured'], null, report: true),
        ];
    }

    /** Attendance sessions running now that the student has not checked in to. */
    private function openCheckIns(User $user, Collection $sectionIds): Collection
    {
        $sessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'active')
            ->with('section.course')
            ->latest('started_at')
            ->get();

        $answered = AttendanceRecord::where('user_id', $user->id)
            ->whereIn('attendance_session_id', $sessions->pluck('id'))
            ->pluck('attendance_session_id');

        return $sessions->reject(fn (AttendanceSession $session) => $answered->contains($session->id))->values();
    }

    /** The latest released marks, assignments and assessments together, newest first. */
    private function recentMarks(User $user, Collection $courseIds): Collection
    {
        $marks = StudentMark::where('user_id', $user->id)
            ->where('is_final', true)
            ->whereIn('assignment_id', Assignment::whereIn('course_id', $courseIds)->pluck('id'))
            ->with('assignment.course')
            ->latest('finalized_at')
            ->limit(4)
            ->get()
            ->map(fn (StudentMark $mark) => [
                'title' => $mark->assignment?->title,
                'course' => $mark->assignment?->course,
                'raw' => (float) $mark->total_marks,
                'max' => (float) $mark->max_marks,
                'percentage' => $mark->percentage !== null ? (float) $mark->percentage : null,
                'grade' => $mark->grade,
                'released_at' => $mark->finalized_at,
            ]);

        $scores = AssessmentScore::where('user_id', $user->id)
            ->where('is_released', true)
            ->whereHas('assessment', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->with('assessment.course')
            ->latest('released_at')
            ->limit(4)
            ->get()
            ->map(fn (AssessmentScore $score) => [
                'title' => $score->assessment?->title,
                'course' => $score->assessment?->course,
                'raw' => (float) $score->raw_marks,
                'max' => (float) $score->max_marks,
                'percentage' => $score->percentage !== null ? (float) $score->percentage : null,
                'grade' => null,
                'released_at' => $score->released_at,
            ]);

        return collect($marks)->concat($scores)
            ->sortByDesc(fn (array $mark) => $mark['released_at']?->timestamp ?? 0)
            ->take(4)
            ->values();
    }

    /**
     * The next five assessments the student must hand in, for the highlight at the
     * top of the dashboard: submitted ones turn green, and the rest go from blue
     * to amber within a week of the due date and red within 48 hours of it.
     */
    private function dueAssessments(User $user): Collection
    {
        $courseIds = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('section:id,course_id')
            ->get()
            ->pluck('section.course_id')
            ->filter()
            ->unique();

        $assessments = Assessment::whereIn('course_id', $courseIds)
            ->where('requires_submission', true)
            ->whereIn('status', ['active', 'completed'])
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->with('course')
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        $groupIds = $assessments
            ->map(fn (Assessment $assessment) => $assessment->groupForUser($user->id)?->id)
            ->filter()
            ->values();

        $submittedIds = AssessmentSubmission::whereIn('assessment_id', $assessments->pluck('id'))
            ->where(function ($q) use ($user, $groupIds) {
                $q->where('user_id', $user->id);

                if ($groupIds->isNotEmpty()) {
                    $q->orWhereIn('student_group_id', $groupIds);
                }
            })
            ->pluck('assessment_id');

        return $assessments->map(function (Assessment $assessment) use ($submittedIds) {
            $submitted = $submittedIds->contains($assessment->id);
            $hoursLeft = now()->diffInHours($assessment->due_date);

            return [
                'assessment' => $assessment,
                'submitted' => $submitted,
                'tone' => match (true) {
                    $submitted => 'submitted',
                    $hoursLeft < 48 => 'urgent',
                    $hoursLeft < 24 * 7 => 'soon',
                    default => 'later',
                },
            ];
        });
    }
}
