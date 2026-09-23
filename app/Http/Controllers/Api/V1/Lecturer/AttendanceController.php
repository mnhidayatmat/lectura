<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Lecturer\AttendanceRecordResource;
use App\Http\Resources\Api\V1\Lecturer\AttendanceSectionResource;
use App\Http\Resources\Api\V1\Lecturer\AttendanceSessionDetailResource;
use App\Http\Resources\Api\V1\Lecturer\AttendanceSessionResource;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\User;
use App\Services\Attendance\AttendanceSessionService;
use App\Services\Attendance\QrCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    use AuthorizesLecturerAccess;

    public const SESSION_TYPES = ['lecture', 'tutorial', 'lab', 'extra', 'replacement'];

    public const RECENT_SESSION_LIMIT = 30;

    public function __construct(
        protected QrCodeService $qrService,
        protected AttendanceSessionService $sessionService,
    ) {}

    public function index(): JsonResponse
    {
        $this->ensureLecturer();

        $sectionIds = $this->allAccessibleSectionIds();

        $activeSessions = $this->sessionsQuery($sectionIds)
            ->where('status', 'active')
            ->latest('started_at')
            ->get();

        $recentSessions = $this->sessionsQuery($sectionIds)
            ->where('status', 'ended')
            ->latest('started_at')
            ->limit(self::RECENT_SESSION_LIMIT)
            ->get();

        $activeBySection = $activeSessions->pluck('id', 'section_id');

        $sections = Section::whereIn('id', $sectionIds)
            ->where('is_active', true)
            ->with('course:id,code,title')
            ->get()
            ->filter(fn (Section $section) => $section->course !== null)
            ->each(fn (Section $section) => $section->setAttribute('active_session_id', $activeBySection[$section->id] ?? null))
            ->sortBy(fn (Section $section) => $section->course->code.' '.$section->name)
            ->values();

        return response()->json([
            'data' => [
                'active_sessions' => AttendanceSessionResource::collection($activeSessions),
                'recent_sessions' => AttendanceSessionResource::collection($recentSessions),
                'sections' => AttendanceSectionResource::collection($sections),
                'session_types' => self::SESSION_TYPES,
            ],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $this->ensureLecturer();

        $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'session_type' => ['required', 'in:lecture,tutorial,lab,extra,replacement'],
            'week_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenant = app('current_tenant');
        $section = Section::with(['course', 'lecturers'])->find($request->section_id);

        if (! $section || ! $section->course) {
            abort(404, 'Section not found.');
        }

        if (! $this->canAccessSection($section)) {
            abort(403, 'You do not have access to this section.');
        }

        if ($section->course->status === 'archived') {
            return response()->json([
                'message' => 'This course is archived, so no new attendance sessions can be started. Reopen the semester first.',
            ], 409);
        }

        $existing = AttendanceSession::where('section_id', $section->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'An active session already exists for this section.',
                'data' => ['session_id' => $existing->id],
            ], 409);
        }

        $session = AttendanceSession::create([
            'tenant_id' => $tenant->id,
            'section_id' => $section->id,
            'lecturer_id' => auth()->id(),
            'session_type' => $request->session_type,
            'week_number' => $request->week_number,
            'qr_secret' => Str::random(64),
            'qr_mode' => 'rotating',
            'qr_rotation_seconds' => config('lectura.attendance.qr_rotation_seconds', 30),
            'late_threshold_minutes' => config('lectura.attendance.late_threshold_minutes', 15),
            'status' => 'active',
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Attendance session started.',
            'data' => $this->detail($session),
        ], 201);
    }

    public function show(AttendanceSession $session): AttendanceSessionDetailResource
    {
        $this->ensureLecturer();
        $this->authorizeSession($session);

        return $this->detail($session);
    }

    /**
     * Fresh rotating QR payload plus live check-ins (poll this from the QR screen).
     */
    public function token(AttendanceSession $session): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSession($session);

        if (! $session->isActive()) {
            return response()->json(['message' => 'This attendance session has ended.'], 409);
        }

        $rotation = max(1, (int) $session->qr_rotation_seconds);
        $now = time();

        $token = $this->qrService->generateToken($session->qr_secret, $rotation);
        $payload = $this->qrService->buildPayload($session->id, $token);

        $records = $session->records()
            ->whereIn('status', ['present', 'late'])
            ->with('user:id,name')
            ->orderByDesc('checked_in_at')
            ->get();

        return response()->json([
            'data' => [
                'session_id' => $session->id,
                'payload' => $payload,
                'qr_mode' => $session->qr_mode,
                'rotation_seconds' => $rotation,
                'expires_in' => $rotation - ($now % $rotation),
                'server_time' => now()->toIso8601String(),
                'checked_in' => $records->count(),
                'total' => $session->section->activeStudents()->count(),
                'records' => $records->map(fn (AttendanceRecord $record) => [
                    'id' => $record->id,
                    'user_id' => $record->user_id,
                    'name' => $record->user?->name,
                    'status' => $record->status,
                    'time' => $record->checked_in_at?->format('H:i:s'),
                    'checked_in_at' => $record->checked_in_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function end(AttendanceSession $session): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSession($session);

        if (! $session->isActive()) {
            return response()->json(['message' => 'This session has already ended.'], 409);
        }

        $markedAbsent = $this->sessionService->end($session);

        return response()->json([
            'message' => 'Session ended. '.$markedAbsent.' students marked absent.',
            'data' => [
                'marked_absent' => $markedAbsent,
                'session' => $this->detail($session),
            ],
        ]);
    }

    public function reopen(AttendanceSession $session): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSession($session);

        if ($session->status !== 'ended') {
            return response()->json(['message' => 'Only ended sessions can be reopened.'], 409);
        }

        if ($locked = $this->lockedResponse($session)) {
            return $locked;
        }

        $otherActive = AttendanceSession::where('section_id', $session->section_id)
            ->where('status', 'active')
            ->where('id', '!=', $session->id)
            ->value('id');

        if ($otherActive) {
            return response()->json([
                'message' => 'Another attendance session is already active for this section.',
                'data' => ['session_id' => $otherActive],
            ], 409);
        }

        // Keep lecturer overrides and real scans; drop only auto-generated absences
        $session->records()
            ->where('status', 'absent')
            ->where('method', 'manual')
            ->whereNull('checked_in_at')
            ->whereNull('override_by')
            ->delete();

        $session->update([
            'status' => 'active',
            'ended_at' => null,
        ]);

        return response()->json([
            'message' => 'Session reopened. Students can now scan again.',
            'data' => $this->detail($session),
        ]);
    }

    public function update(Request $request, AttendanceSession $session): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSession($session);

        if ($locked = $this->lockedResponse($session)) {
            return $locked;
        }

        $validated = $request->validate([
            'session_type' => ['required', 'in:lecture,tutorial,lab,extra,replacement'],
            'week_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $session->update($validated);

        return response()->json([
            'message' => 'Session details updated.',
            'data' => $this->detail($session),
        ]);
    }

    public function override(Request $request, AttendanceSession $session, AttendanceRecord $record): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSession($session);

        if ($record->attendance_session_id !== $session->id) {
            abort(404, 'Attendance record not found.');
        }

        if ($locked = $this->lockedResponse($session)) {
            return $locked;
        }

        $request->validate([
            'status' => ['required', 'in:present,late,absent,excused'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $record->update([
            'status' => $request->status,
            'override_by' => auth()->id(),
            'override_reason' => $request->reason,
        ]);

        $record->load(['user', 'excuse']);
        $this->decorateRecords(collect([$record]));

        return response()->json([
            'message' => 'Attendance updated.',
            'data' => new AttendanceRecordResource($record),
        ]);
    }

    public function destroy(AttendanceSession $session): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSession($session);

        if ($session->isActive()) {
            return response()->json(['message' => 'Cannot delete an active session. End it first.'], 409);
        }

        if ($locked = $this->lockedResponse($session)) {
            return $locked;
        }

        $sessionId = $session->id;

        $session->records()->delete();
        $session->delete();

        return response()->json([
            'message' => 'Attendance session deleted.',
            'data' => ['id' => $sessionId],
        ]);
    }

    private function lockedResponse(AttendanceSession $session): ?JsonResponse
    {
        if (! $session->isLocked()) {
            return null;
        }

        return response()->json([
            'message' => $session->lockMessage(),
            'data' => ['lock_reason' => $session->lockReason()],
        ], 409);
    }

    private function sessionsQuery(Collection $sectionIds): Builder
    {
        return AttendanceSession::whereIn('section_id', $sectionIds)
            ->with([
                'section' => fn ($query) => $query->withCount('activeStudents'),
                'section.course:id,code,title,status',
            ])
            ->withCount([
                'records as present_count' => fn ($query) => $query->where('status', 'present'),
                'records as late_count' => fn ($query) => $query->where('status', 'late'),
                'records as absent_count' => fn ($query) => $query->where('status', 'absent'),
                'records as excused_count' => fn ($query) => $query->where('status', 'excused'),
            ]);
    }

    private function detail(AttendanceSession $session): AttendanceSessionDetailResource
    {
        $session->load([
            'section' => fn ($query) => $query->withCount('activeStudents'),
            'section.course:id,code,title,status',
            'section.activeStudents',
            'records.user',
            'records.excuse',
        ]);

        $records = $session->records;
        $students = $session->section?->activeStudents ?? collect();
        $idNumbers = $this->decorateRecords($records, $students->pluck('id'));

        $session->setRelation(
            'records',
            $records->sortBy(fn (AttendanceRecord $record) => mb_strtolower($record->user?->name ?? ''))->values()
        );

        $notCheckedIn = $session->isActive()
            ? $students->whereNotIn('id', $records->pluck('user_id'))
                ->sortBy(fn (User $student) => mb_strtolower($student->name))
                ->map(fn (User $student) => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'student_id_number' => $idNumbers[$student->id] ?? null,
                ])
                ->values()
                ->all()
            : [];

        return (new AttendanceSessionDetailResource($session))->withNotCheckedIn($notCheckedIn);
    }

    /**
     * Sets transient display attributes used by AttendanceRecordResource. Returns the student ID map.
     */
    private function decorateRecords(Collection $records, ?Collection $extraUserIds = null): Collection
    {
        $idNumbers = $this->studentIdNumbers($records->pluck('user_id')->merge($extraUserIds ?? []));
        $overriders = User::whereIn('id', $records->pluck('override_by')->filter()->unique())->pluck('name', 'id');

        $records->each(function (AttendanceRecord $record) use ($idNumbers, $overriders) {
            $record->setAttribute('student_id_number', $idNumbers[$record->user_id] ?? null);
            $record->setAttribute('override_by_name', $record->override_by ? ($overriders[$record->override_by] ?? null) : null);
        });

        return $idNumbers;
    }
}
