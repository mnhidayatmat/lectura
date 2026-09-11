<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\StudentPresenter;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentSubmission;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\SectionStudent;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $enrollments = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('section')
            ->with('section.course.lecturer')
            ->get()
            ->filter(fn (SectionStudent $enrollment) => $enrollment->section?->course !== null);

        $courses = $enrollments->groupBy(fn (SectionStudent $e) => $e->section->course_id)
            ->map(fn ($group) => $group->first()->section->course)
            ->values();

        $sectionIds = $enrollments->pluck('section_id');
        $courseIds = $courses->pluck('id');

        $activeSessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'active')
            ->with('section.course')
            ->latest('started_at')
            ->get();

        $myRecords = AttendanceRecord::where('user_id', $user->id)
            ->whereIn('attendance_session_id', $activeSessions->pluck('id'))
            ->get()
            ->keyBy('attendance_session_id');

        return response()->json([
            'data' => [
                'courses_count' => $courses->count(),
                'courses' => $courses->take(5)->map(fn (Course $course) => [
                    ...StudentPresenter::course($course),
                    'lecturer_name' => $course->lecturer?->name,
                ])->values(),
                'active_attendance_sessions' => $activeSessions->map(fn (AttendanceSession $session) => [
                    'id' => $session->id,
                    'course' => StudentPresenter::course($session->section?->course),
                    'section_name' => $session->section?->name,
                    'session_type' => $session->session_type,
                    'week_number' => $session->week_number,
                    'started_at' => $session->started_at?->toIso8601String(),
                    'my_status' => $myRecords->get($session->id)?->status,
                ])->values(),
                'upcoming_deadlines' => $this->upcomingDeadlines($user, $courseIds),
                'recent_marks' => $this->recentMarks($user, $courseIds),
                'unread_notifications_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    private function upcomingDeadlines(User $user, Collection $courseIds): array
    {
        $assignments = Assignment::whereIn('course_id', $courseIds)
            ->where('status', 'published')
            ->whereNotNull('deadline')
            ->where('deadline', '>=', now())
            ->with('course')
            ->orderBy('deadline')
            ->limit(5)
            ->get();

        $submittedAssignmentIds = Submission::where('user_id', $user->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->pluck('assignment_id');

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

        $submittedAssessmentIds = AssessmentSubmission::whereIn('assessment_id', $assessments->pluck('id'))
            ->where(function ($q) use ($user, $groupIds) {
                $q->where('user_id', $user->id);

                if ($groupIds->isNotEmpty()) {
                    $q->orWhereIn('student_group_id', $groupIds);
                }
            })
            ->pluck('assessment_id');

        return $assignments
            ->map(fn (Assignment $assignment) => [
                'kind' => 'assignment',
                'id' => $assignment->id,
                'title' => $assignment->title,
                'course' => StudentPresenter::course($assignment->course),
                'due_at' => $assignment->deadline->toIso8601String(),
                'submitted' => $submittedAssignmentIds->contains($assignment->id),
            ])
            ->concat($assessments->map(fn (Assessment $assessment) => [
                'kind' => 'assessment',
                'id' => $assessment->id,
                'title' => $assessment->title,
                'course' => StudentPresenter::course($assessment->course),
                'due_at' => $assessment->due_date->toIso8601String(),
                'submitted' => $submittedAssessmentIds->contains($assessment->id),
            ]))
            ->sortBy('due_at')
            ->take(5)
            ->values()
            ->all();
    }

    private function recentMarks(User $user, Collection $courseIds): array
    {
        $marks = StudentMark::where('user_id', $user->id)
            ->where('is_final', true)
            ->whereIn('assignment_id', Assignment::whereIn('course_id', $courseIds)->pluck('id'))
            ->with('assignment.course')
            ->latest('finalized_at')
            ->limit(3)
            ->get()
            ->map(fn (StudentMark $mark) => [
                'kind' => 'assignment',
                'id' => $mark->id,
                'title' => $mark->assignment?->title,
                'course' => StudentPresenter::course($mark->assignment?->course),
                'raw_marks' => (float) $mark->total_marks,
                'max_marks' => (float) $mark->max_marks,
                'percentage' => StudentPresenter::decimal($mark->percentage),
                'grade' => $mark->grade,
                'released_at' => $mark->finalized_at?->toIso8601String(),
            ]);

        $scores = AssessmentScore::where('user_id', $user->id)
            ->where('is_released', true)
            ->whereHas('assessment', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->with('assessment.course')
            ->latest('released_at')
            ->limit(3)
            ->get()
            ->map(fn (AssessmentScore $score) => [
                'kind' => 'assessment',
                'id' => $score->id,
                'title' => $score->assessment?->title,
                'course' => StudentPresenter::course($score->assessment?->course),
                'raw_marks' => (float) $score->raw_marks,
                'max_marks' => (float) $score->max_marks,
                'percentage' => StudentPresenter::decimal($score->percentage),
                'grade' => null,
                'released_at' => $score->released_at?->toIso8601String(),
            ]);

        return collect($marks)
            ->concat($scores)
            ->sortByDesc('released_at')
            ->take(3)
            ->values()
            ->all();
    }
}
