<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\StudentMark;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $courses = Course::where('lecturer_id', $user->id)->with('sections')->get();

        return view('tenant.analytics.index', compact('courses'));
    }

    public function course(string $tenantSlug, Course $course): View
    {
        if ($course->lecturer_id !== auth()->id()) {
            abort(403);
        }

        $course->load(['sections.activeStudents', 'learningOutcomes']);

        $sectionIds = $course->sections->pluck('id');
        $studentIds = SectionStudent::whereIn('section_id', $sectionIds)
            ->where('is_active', true)->pluck('user_id')->unique();

        // Marks data
        $marks = StudentMark::where('assignment_id', '!=', null)
            ->whereIn('user_id', $studentIds)
            ->where('tenant_id', app('current_tenant')->id)
            ->with(['assignment', 'user'])
            ->get();

        // Attendance data
        $sessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->with('records')
            ->get();

        // Calculate stats
        $avgMark = $marks->count() > 0 ? round($marks->avg('percentage'), 1) : null;
        $totalStudents = $studentIds->count();

        // Attendance rate
        $totalAttendance = 0;
        $totalPossible = 0;
        foreach ($sessions as $s) {
            $present = $s->records->whereIn('status', ['present', 'late'])->count();
            $total = $s->records->count();
            $totalAttendance += $present;
            $totalPossible += $total;
        }
        $attendanceRate = $totalPossible > 0 ? round($totalAttendance / $totalPossible * 100, 1) : null;

        // Weak students (below 40%)
        $studentAvgs = $marks->groupBy('user_id')->map(fn($group) => [
            'user' => $group->first()->user,
            'avg_percentage' => round($group->avg('percentage'), 1),
            'count' => $group->count(),
        ])->sortBy('avg_percentage');

        $weakStudents = $studentAvgs->filter(fn($s) => $s['avg_percentage'] < 40)->values();
        $topStudents = $studentAvgs->sortByDesc('avg_percentage')->take(5)->values();

        // Per-assignment performance
        $assignmentStats = $marks->groupBy('assignment_id')->map(function ($group) {
            return [
                'title' => $group->first()->assignment->title ?? 'Unknown',
                'avg' => round($group->avg('percentage'), 1),
                'min' => round($group->min('percentage'), 1),
                'max' => round($group->max('percentage'), 1),
                'count' => $group->count(),
            ];
        })->values();

        // Attendance per student
        $studentAttendance = [];
        foreach ($studentIds as $sid) {
            $attended = AttendanceRecord::whereIn('attendance_session_id', $sessions->pluck('id'))
                ->where('user_id', $sid)
                ->whereIn('status', ['present', 'late'])
                ->count();
            $studentAttendance[$sid] = $sessions->count() > 0 ? round($attended / $sessions->count() * 100, 1) : 0;
        }

        return view('tenant.analytics.course', compact(
            'course', 'avgMark', 'totalStudents', 'attendanceRate',
            'weakStudents', 'topStudents', 'assignmentStats',
            'sessions', 'marks', 'studentAttendance'
        ));
    }
}
