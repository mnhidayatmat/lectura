<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Lecturer\AttendanceSessionResource;
use App\Http\Resources\Api\V1\Lecturer\CourseSummaryResource;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use AuthorizesLecturerAccess;

    public const RECENT_COURSE_LIMIT = 5;

    /**
     * Same figures as the lecturer branch of the web dashboard.
     */
    public function show(Request $request): JsonResponse
    {
        $this->ensureLecturer();

        $tenant = app('current_tenant');
        $user = $request->user();

        $ownedCourseIds = Course::where('lecturer_id', $user->id)->pluck('id');
        $sectionCourseIds = Section::whereHas('lecturers', fn ($q) => $q->where('user_id', $user->id))->pluck('course_id');
        $allCourseIds = $ownedCourseIds->merge($sectionCourseIds)->unique();

        $courses = Course::whereIn('id', $allCourseIds)
            ->withCount('sections')
            ->with(['academicTerm', 'faculty'])
            ->latest()
            ->get();
        $courseCount = $courses->where('status', 'active')->count();

        $sectionIds = Section::whereIn('course_id', $courses->pluck('id'))
            ->where('is_active', true)
            ->pluck('id');
        $studentCount = SectionStudent::whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->distinct('user_id')
            ->count('user_id');

        $avgAttendance = null;
        $endedSessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->withCount([
                'records as attended_count' => fn ($q) => $q->whereIn('status', ['present', 'late']),
                'records as total_count',
            ])
            ->get();

        if ($endedSessions->isNotEmpty()) {
            $rates = $endedSessions
                ->filter(fn ($s) => $s->total_count > 0)
                ->map(fn ($s) => $s->attended_count / $s->total_count);
            if ($rates->isNotEmpty()) {
                $avgAttendance = (int) round($rates->avg() * 100);
            }
        }

        $now = now();
        $today = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        $todaySchedule = Section::whereIn('course_id', $courses->pluck('id'))
            ->whereNotNull('schedule')
            ->where('is_active', true)
            ->with('course:id,code,title')
            ->get()
            ->flatMap(fn (Section $section) => collect($section->schedule ?? [])
                ->filter(fn ($slot) => ($slot['day'] ?? '') === $today
                    && ! empty($slot['start_time']) && ! empty($slot['end_time']))
                ->map(fn ($slot) => [
                    'course' => [
                        'id' => $section->course_id,
                        'code' => $section->course?->code,
                        'title' => $section->course?->title,
                    ],
                    'section' => ['id' => $section->id, 'name' => $section->name],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'location' => $slot['location'] ?? null,
                    'type' => $slot['type'] ?? 'lecture',
                    'is_now' => $currentTime >= $slot['start_time'] && $currentTime < $slot['end_time'],
                    'is_past' => $currentTime >= $slot['end_time'],
                ]))
            ->sortBy('start_time')
            ->values();

        $activeSessions = AttendanceSession::whereIn('section_id', $this->allAccessibleSectionIds())
            ->where('status', 'active')
            ->with([
                'section' => fn ($query) => $query->withCount('activeStudents'),
                'section.course:id,code,title',
            ])
            ->withCount([
                'records as present_count' => fn ($q) => $q->where('status', 'present'),
                'records as late_count' => fn ($q) => $q->where('status', 'late'),
                'records as absent_count' => fn ($q) => $q->where('status', 'absent'),
                'records as excused_count' => fn ($q) => $q->where('status', 'excused'),
            ])
            ->latest('started_at')
            ->get();

        return response()->json([
            'data' => [
                'tenant_name' => $tenant->name,
                'date' => $now->toDateString(),
                'day' => $now->format('l'),
                'server_time' => $now->toIso8601String(),
                'stats' => [
                    'active_courses' => $courseCount,
                    'students' => $studentCount,
                    'avg_attendance' => $avgAttendance,
                ],
                'today_schedule' => $todaySchedule,
                'active_sessions' => AttendanceSessionResource::collection($activeSessions),
                'recent_courses' => CourseSummaryResource::collection($courses->take(self::RECENT_COURSE_LIMIT)),
            ],
        ]);
    }
}
