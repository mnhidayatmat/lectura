<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RandomWheelController extends Controller
{
    use AuthorizesCourseAccess;

    public function index(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $courseIds = $this->accessibleCourseIds();
        $courses = Course::whereIn('id', $courseIds)->get();

        // Load only sections this lecturer can access per course
        foreach ($courses as $course) {
            $course->setRelation('sections', $this->lecturerSections($course)->get());
        }

        $sectionIds = $this->allAccessibleSectionIds();

        $latestSession = AttendanceSession::whereIn('section_id', $sectionIds)
            ->orderByDesc('started_at')
            ->with('section')
            ->first();

        $latestDefaults = null;
        if ($latestSession) {
            $latestDefaults = [
                'courseId' => (string) $latestSession->section->course_id,
                'sectionId' => (string) $latestSession->section_id,
                'sessionId' => (string) $latestSession->id,
            ];
        }

        return view('tenant.random-wheel.index', compact('tenant', 'courses', 'latestDefaults'));
    }

    public function sessions(Request $request, string $tenantSlug): JsonResponse
    {
        $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
        ]);

        $section = Section::findOrFail($request->section_id);
        $this->authorizeSection($section);

        $sessions = AttendanceSession::where('section_id', $section->id)
            ->orderByDesc('started_at')
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => ($s->isActive() ? '🟢 LIVE — ' : '') .
                    'W' . ($s->week_number ?? '?') . ' ' . ucfirst($s->session_type) .
                    ' — ' . $s->started_at->format('d M Y, H:i'),
                'is_active' => $s->isActive(),
                'checked_in' => $s->checkedInCount(),
            ]);

        return response()->json($sessions);
    }

    public function presentStudents(Request $request, string $tenantSlug): JsonResponse
    {
        $request->validate([
            'session_id' => ['required', 'exists:attendance_sessions,id'],
            'include_late' => ['nullable'],
        ]);

        $session = AttendanceSession::with('section.course')->findOrFail($request->session_id);
        $this->authorizeCourseAccess($session->section->course);

        $statuses = ['present'];
        if ($request->input('include_late') === '1' || $request->input('include_late') === 'true') {
            $statuses[] = 'late';
        }

        $students = AttendanceRecord::where('attendance_session_id', $session->id)
            ->whereIn('status', $statuses)
            ->with('user:id,name')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->user_id,
                'name' => $r->user->name,
                'status' => $r->status,
            ])
            ->values();

        return response()->json([
            'students' => $students,
            'session' => [
                'id' => $session->id,
                'week' => $session->week_number,
                'type' => $session->session_type,
                'started_at' => $session->started_at->format('d M Y, H:i'),
                'is_active' => $session->isActive(),
                'section' => $session->section->name,
            ],
        ]);
    }

    private function authorizeSection(Section $section): void
    {
        $this->authorizeCourseAccess($section->course);
    }
}
