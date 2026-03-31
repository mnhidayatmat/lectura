<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

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
    public function index(): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $query = Course::with('sections');
        if (! $user->is_super_admin) {
            $query->where('lecturer_id', $user->id);
        }
        $courses = $query->get();

        // Find the latest attendance session across the user's courses
        $courseIds = $courses->pluck('id');
        $sectionIds = Section::whereIn('course_id', $courseIds)->pluck('id');

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
        $this->authorizeCourse($section->course);

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
        $this->authorizeCourse($session->section->course);

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

    private function authorizeCourse(Course $course): void
    {
        $user = auth()->user();
        if (! $user->is_super_admin && $course->lecturer_id !== $user->id) {
            abort(403);
        }
    }
}
