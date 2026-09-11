<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WheelController extends Controller
{
    use AuthorizesLecturerAccess;

    public const SESSION_LIMIT = 20;

    /**
     * Course/section pickers and the most recent session as defaults (RandomWheelController@index).
     */
    public function index(): JsonResponse
    {
        $this->ensureLecturer();

        $courses = Course::whereIn('id', $this->accessibleCourseIds())
            ->orderBy('code')
            ->get()
            ->each(fn (Course $course) => $course->setRelation(
                'sections',
                $this->lecturerSections($course)->orderBy('name')->get()
            ));

        $latestSession = AttendanceSession::whereIn('section_id', $this->allAccessibleSectionIds())
            ->orderByDesc('started_at')
            ->with('section')
            ->first();

        return response()->json([
            'data' => [
                'courses' => $courses->map(fn (Course $course) => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'sections' => $course->sections->map(fn (Section $section) => [
                        'id' => $section->id,
                        'name' => $section->name,
                        'code' => $section->code,
                        'is_active' => (bool) $section->is_active,
                    ])->values(),
                ])->values(),
                'defaults' => $latestSession ? [
                    'course_id' => $latestSession->section->course_id,
                    'section_id' => $latestSession->section_id,
                    'session_id' => $latestSession->id,
                ] : null,
            ],
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $this->ensureLecturer();

        $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
        ]);

        $section = Section::with('course')->find($request->section_id);

        if (! $section || ! $section->course) {
            abort(404, 'Section not found.');
        }

        $this->authorizeCourse($section->course);

        $sessions = AttendanceSession::where('section_id', $section->id)
            ->withCount(['records as checked_in_count' => fn ($query) => $query->whereIn('status', ['present', 'late'])])
            ->orderByDesc('started_at')
            ->limit(self::SESSION_LIMIT)
            ->get();

        return response()->json([
            'data' => $sessions->map(fn (AttendanceSession $session) => [
                'id' => $session->id,
                'label' => ($session->isActive() ? 'LIVE — ' : '').
                    'W'.($session->week_number ?? '?').' '.ucfirst($session->session_type).
                    ' — '.$session->started_at->format('d M Y, H:i'),
                'session_type' => $session->session_type,
                'week_number' => $session->week_number !== null ? (int) $session->week_number : null,
                'started_at' => $session->started_at?->toIso8601String(),
                'is_active' => $session->isActive(),
                'checked_in' => (int) $session->checked_in_count,
            ])->values(),
        ]);
    }

    public function presentStudents(Request $request): JsonResponse
    {
        $this->ensureLecturer();

        $request->validate([
            'session_id' => ['required', 'exists:attendance_sessions,id'],
            'include_late' => ['nullable'],
        ]);

        $session = AttendanceSession::with('section.course')->find($request->session_id);

        if (! $session || ! $session->section?->course) {
            abort(404, 'Attendance session not found.');
        }

        $this->authorizeCourse($session->section->course);

        $statuses = ['present'];
        if ($request->boolean('include_late')) {
            $statuses[] = 'late';
        }

        $students = AttendanceRecord::where('attendance_session_id', $session->id)
            ->whereIn('status', $statuses)
            ->with('user:id,name')
            ->get()
            ->filter(fn (AttendanceRecord $record) => $record->user !== null)
            ->map(fn (AttendanceRecord $record) => [
                'id' => $record->user_id,
                'name' => $record->user->name,
                'status' => $record->status,
            ])
            ->values();

        return response()->json([
            'data' => [
                'students' => $students,
                'session' => [
                    'id' => $session->id,
                    'week_number' => $session->week_number !== null ? (int) $session->week_number : null,
                    'session_type' => $session->session_type,
                    'started_at' => $session->started_at?->toIso8601String(),
                    'is_active' => $session->isActive(),
                    'section_name' => $session->section->name,
                    'course_code' => $session->section->course->code,
                ],
            ],
        ]);
    }
}
