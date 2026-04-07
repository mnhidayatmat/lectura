<?php

declare(strict_types=1);

namespace App\Services\Performance;

use App\Models\ActiveLearningResponse;
use App\Models\ActiveLearningSession;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\QuizParticipant;
use App\Models\QuizSession;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\StudentMark;
use App\Models\User;
use Illuminate\Support\Collection;

class PerformanceAggregatorService
{
    /**
     * Get full course performance data for lecturer dashboard.
     */
    public function getCoursePerformance(Course $course, ?Section $section = null, ?Collection $allowedSections = null): array
    {
        $course->load(['learningOutcomes']);

        $sections = $allowedSections ?? $course->sections()->get();

        $sectionIds = $section
            ? collect([$section->id])
            : $sections->pluck('id');

        $studentIds = SectionStudent::whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->pluck('user_id')
            ->unique();

        $students = User::whereIn('id', $studentIds)->get()->keyBy('id');
        $tenantId = app('current_tenant')->id;

        // Marks
        $marks = StudentMark::where('tenant_id', $tenantId)
            ->whereHas('assignment', fn ($q) => $q->where('course_id', $course->id))
            ->whereIn('user_id', $studentIds)
            ->with(['assignment', 'user'])
            ->get();

        // Attendance
        $attendanceSessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->with('records')
            ->orderBy('started_at')
            ->get();

        // Quiz
        $quizSessions = QuizSession::where('tenant_id', $tenantId)
            ->whereIn('section_id', $sectionIds)
            ->with(['participants' => fn ($q) => $q->whereIn('user_id', $studentIds)])
            ->get();

        // Active Learning
        $alResponses = $this->getActiveLearningResponses($course, $studentIds);

        // Per-student aggregation
        $studentPerformance = $this->aggregatePerStudent(
            $students, $marks, $attendanceSessions, $quizSessions, $alResponses
        );

        // CLO attainment
        $cloAttainment = $this->calculateCloAttainment($course, $marks);

        // Assignment stats
        $assignmentStats = $marks->groupBy('assignment_id')->map(fn ($group) => [
            'title' => $group->first()->assignment->title ?? 'Unknown',
            'avg' => round($group->avg('percentage'), 1),
            'min' => round($group->min('percentage'), 1),
            'max' => round($group->max('percentage'), 1),
            'count' => $group->count(),
            'clo_ids' => $group->first()->assignment->clo_ids ?? [],
        ])->values();

        // Quiz stats
        $quizStats = $quizSessions->map(fn ($qs) => [
            'title' => $qs->title,
            'category' => $qs->category,
            'avg_score' => $qs->participants->count() > 0
                ? round($qs->participants->avg('total_score'), 1)
                : null,
            'participant_count' => $qs->participants->count(),
            'date' => $qs->started_at,
        ])->values();

        // Course-level summaries
        $avgMark = $marks->count() > 0 ? round($marks->avg('percentage'), 1) : null;
        $avgQuiz = $quizSessions->flatMap->participants->count() > 0
            ? round($quizSessions->flatMap->participants->avg('total_score'), 1)
            : null;

        $totalAttendance = 0;
        $totalPossible = 0;
        foreach ($attendanceSessions as $s) {
            $totalAttendance += $s->records->whereIn('status', ['present', 'late'])->count();
            $totalPossible += $s->records->count();
        }
        $attendanceRate = $totalPossible > 0 ? round($totalAttendance / $totalPossible * 100, 1) : null;

        $atRisk = $studentPerformance->filter(fn ($s) => ($s['composite_score'] ?? 0) < 40)->values();

        return [
            'total_students' => $students->count(),
            'avg_mark' => $avgMark,
            'avg_quiz' => $avgQuiz,
            'attendance_rate' => $attendanceRate,
            'at_risk_count' => $atRisk->count(),
            'students' => $studentPerformance,
            'at_risk_students' => $atRisk,
            'assignment_stats' => $assignmentStats,
            'quiz_stats' => $quizStats,
            'attendance_sessions' => $attendanceSessions,
            'clo_attainment' => $cloAttainment,
            'sections' => $sections,
        ];
    }

    /**
     * Get a single student's performance across all enrolled courses.
     */
    public function getStudentOverview(User $student): array
    {
        $tenantId = app('current_tenant')->id;
        $sectionIds = SectionStudent::where('user_id', $student->id)
            ->where('is_active', true)
            ->pluck('section_id');

        $sections = Section::whereIn('id', $sectionIds)->with('course.learningOutcomes')->get();
        $courses = $sections->pluck('course')->unique('id')->filter();

        $coursePerformance = [];
        foreach ($courses as $course) {
            try {
                $coursePerformance[] = $this->getStudentCoursePerformance($student, $course);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Performance aggregation failed for course', [
                    'course_id' => $course->id,
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
                $coursePerformance[] = [
                    'course' => $course,
                    'avg_mark' => null,
                    'attendance_rate' => null,
                    'avg_quiz' => null,
                    'al_responses' => 0,
                    'marks' => collect(),
                    'quiz_participations' => collect(),
                    'attendance_timeline' => collect(),
                    'clo_attainment' => [],
                    'composite_score' => null,
                ];
            }
        }

        return [
            'student' => $student,
            'courses' => collect($coursePerformance),
            'overall_avg_mark' => collect($coursePerformance)->avg('avg_mark'),
            'overall_attendance' => collect($coursePerformance)->avg('attendance_rate'),
        ];
    }

    /**
     * Get a student's performance in a specific course.
     */
    public function getStudentCoursePerformance(User $student, Course $course): array
    {
        $course->load(['sections', 'learningOutcomes']);
        $tenantId = app('current_tenant')->id;
        $sectionIds = $course->sections->pluck('id');

        // Marks
        $marks = StudentMark::where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->whereHas('assignment', fn ($q) => $q->where('course_id', $course->id))
            ->with('assignment')
            ->get();

        // Attendance
        $attendanceSessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->orderBy('started_at')
            ->get();

        $attendanceRecords = AttendanceRecord::where('user_id', $student->id)
            ->whereIn('attendance_session_id', $attendanceSessions->pluck('id'))
            ->get()
            ->keyBy('attendance_session_id');

        $attended = $attendanceRecords->whereIn('status', ['present', 'late'])->count();
        $attendanceRate = $attendanceSessions->count() > 0
            ? round($attended / $attendanceSessions->count() * 100, 1) : null;

        // Quiz
        $quizSessions = QuizSession::where('tenant_id', $tenantId)
            ->whereIn('section_id', $sectionIds)
            ->get();

        $quizParticipations = QuizParticipant::where('user_id', $student->id)
            ->whereIn('quiz_session_id', $quizSessions->pluck('id'))
            ->with('quizSession')
            ->get();

        $avgQuiz = $quizParticipations->count() > 0
            ? round($quizParticipations->avg('total_score'), 1) : null;

        // Active Learning
        $alSessions = ActiveLearningSession::whereHas('plan', fn ($q) => $q->where('course_id', $course->id))
            ->pluck('id');
        $alResponseCount = ActiveLearningResponse::where('user_id', $student->id)
            ->whereIn('session_id', $alSessions)
            ->count();

        // CLO attainment for this student
        $cloAttainment = [];
        foreach ($course->learningOutcomes as $clo) {
            $cloMarks = $marks->filter(fn ($m) => in_array($clo->id, $m->assignment->clo_ids ?? []));
            $cloAttainment[] = [
                'code' => $clo->code,
                'description' => $clo->description,
                'avg' => $cloMarks->count() > 0 ? round($cloMarks->avg('percentage'), 1) : null,
                'count' => $cloMarks->count(),
            ];
        }

        // Build attendance timeline
        $attendanceTimeline = $attendanceSessions->map(fn ($s) => [
            'week' => $s->week_number,
            'date' => $s->started_at,
            'status' => $attendanceRecords[$s->id]->status ?? 'absent',
        ]);

        $avgMark = $marks->count() > 0 ? round($marks->avg('percentage'), 1) : null;

        return [
            'course' => $course,
            'avg_mark' => $avgMark,
            'attendance_rate' => $attendanceRate,
            'avg_quiz' => $avgQuiz,
            'al_responses' => $alResponseCount,
            'marks' => $marks,
            'quiz_participations' => $quizParticipations,
            'attendance_timeline' => $attendanceTimeline,
            'clo_attainment' => $cloAttainment,
            'composite_score' => $this->computeComposite($avgMark, $attendanceRate, $avgQuiz),
        ];
    }

    protected function aggregatePerStudent(
        Collection $students,
        Collection $marks,
        Collection $attendanceSessions,
        Collection $quizSessions,
        Collection $alResponses,
    ): Collection {
        $marksByStudent = $marks->groupBy('user_id');
        $quizByStudent = $quizSessions->flatMap->participants->groupBy('user_id');
        $alByStudent = $alResponses->groupBy('user_id');

        // Pre-calculate attendance per student
        $attendanceByStudent = [];
        foreach ($attendanceSessions as $session) {
            foreach ($session->records as $record) {
                $uid = $record->user_id;
                if (! isset($attendanceByStudent[$uid])) {
                    $attendanceByStudent[$uid] = ['attended' => 0, 'total' => 0];
                }
                $attendanceByStudent[$uid]['total']++;
                if (in_array($record->status, ['present', 'late'])) {
                    $attendanceByStudent[$uid]['attended']++;
                }
            }
        }

        return $students->map(function ($student) use ($marksByStudent, $quizByStudent, $alByStudent, $attendanceByStudent) {
            $studentMarks = $marksByStudent->get($student->id, collect());
            $studentQuiz = $quizByStudent->get($student->id, collect());
            $studentAl = $alByStudent->get($student->id, collect());
            $att = $attendanceByStudent[$student->id] ?? ['attended' => 0, 'total' => 0];

            $avgMark = $studentMarks->count() > 0 ? round($studentMarks->avg('percentage'), 1) : null;
            $avgQuiz = $studentQuiz->count() > 0 ? round($studentQuiz->avg('total_score'), 1) : null;
            $attendanceRate = $att['total'] > 0 ? round($att['attended'] / $att['total'] * 100, 1) : null;

            return [
                'user' => $student,
                'avg_mark' => $avgMark,
                'avg_quiz' => $avgQuiz,
                'attendance_rate' => $attendanceRate,
                'al_responses' => $studentAl->count(),
                'assessment_count' => $studentMarks->count(),
                'quiz_count' => $studentQuiz->count(),
                'composite_score' => $this->computeComposite($avgMark, $attendanceRate, $avgQuiz),
            ];
        })->sortByDesc('composite_score')->values();
    }

    protected function computeComposite(?float $avgMark, ?float $attendanceRate, ?float $avgQuiz): float
    {
        $components = [];
        $weights = [];

        if ($avgMark !== null) {
            $components[] = $avgMark;
            $weights[] = 0.5;
        }
        if ($attendanceRate !== null) {
            $components[] = $attendanceRate;
            $weights[] = 0.2;
        }
        if ($avgQuiz !== null) {
            $components[] = min($avgQuiz, 100);
            $weights[] = 0.3;
        }

        if (empty($components)) {
            return 0;
        }

        $totalWeight = array_sum($weights);

        return round(collect($components)->zip($weights)->sum(fn ($pair) => $pair[0] * $pair[1]) / $totalWeight, 1);
    }

    protected function calculateCloAttainment(Course $course, Collection $marks): array
    {
        $cloAttainment = [];
        foreach ($course->learningOutcomes as $clo) {
            $cloMarks = $marks->filter(fn ($m) => in_array($clo->id, $m->assignment->clo_ids ?? []));
            $cloAttainment[] = [
                'code' => $clo->code,
                'description' => $clo->description,
                'avg' => $cloMarks->count() > 0 ? round($cloMarks->avg('percentage'), 1) : null,
                'count' => $cloMarks->count(),
                'student_count' => $cloMarks->pluck('user_id')->unique()->count(),
            ];
        }

        return $cloAttainment;
    }

    protected function getActiveLearningResponses(Course $course, Collection $studentIds): Collection
    {
        $sessionIds = ActiveLearningSession::whereHas('plan', fn ($q) => $q->where('course_id', $course->id))
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return ActiveLearningResponse::whereIn('session_id', $sessionIds)
            ->whereIn('user_id', $studentIds)
            ->get();
    }
}
