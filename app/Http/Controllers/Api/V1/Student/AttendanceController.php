<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\StudentPresenter;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\SectionStudent;
use App\Services\Attendance\AttendanceExcuseService;
use App\Services\Attendance\AttendanceWarningService;
use App\Services\Attendance\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        protected QrCodeService $qrService,
        protected AttendanceWarningService $warningService,
        protected AttendanceExcuseService $excuseService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $enrollments = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('section')
            ->with(['section.course.lecturer', 'section.course.attendancePolicy'])
            ->get()
            ->filter(fn (SectionStudent $enrollment) => $enrollment->section?->course !== null);

        $courses = $enrollments->groupBy(fn (SectionStudent $e) => $e->section->course_id)
            ->map(function ($group) use ($user) {
                $course = $group->first()->section->course;

                return [
                    'course' => StudentPresenter::course($course),
                    'lecturer_name' => $course->lecturer?->name,
                    'sections' => $group->map(fn (SectionStudent $e) => $e->section->name)->values(),
                    'summary' => $this->summary(
                        $this->warningService->getAbsenceSummary($course, $user),
                        $course->attendancePolicy,
                    ),
                ];
            })
            ->values();

        return response()->json(['data' => $courses]);
    }

    public function course(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        $mySectionIds = SectionStudent::whereHas('section', fn ($q) => $q->where('course_id', $course->id))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('section_id');

        if ($mySectionIds->isEmpty()) {
            abort(403, 'You are not enrolled in this course.');
        }

        $course->loadMissing(['lecturer', 'attendancePolicy']);
        $policy = $course->attendancePolicy;

        $sessions = AttendanceSession::whereIn('section_id', $mySectionIds)
            ->where('status', 'ended')
            ->with('section.course')
            ->orderByDesc('started_at')
            ->get();

        $records = AttendanceRecord::where('user_id', $user->id)
            ->whereIn('attendance_session_id', $sessions->pluck('id'))
            ->with('excuse')
            ->get()
            ->keyBy('attendance_session_id');

        return response()->json([
            'data' => [
                'course' => [
                    ...StudentPresenter::course($course),
                    'lecturer_name' => $course->lecturer?->name,
                ],
                'summary' => $this->summary($this->warningService->getAbsenceSummary($course, $user), $policy),
                'policy' => $policy ? [
                    'mode' => $policy->mode,
                    'bar_threshold' => StudentPresenter::decimal($policy->bar_threshold),
                    'include_late_as_absent' => (bool) $policy->include_late_as_absent,
                ] : null,
                'sessions' => $sessions->map(function (AttendanceSession $session) use ($records) {
                    $record = $records->get($session->id);

                    return [
                        'id' => $session->id,
                        'week_number' => $session->week_number,
                        'session_type' => $session->session_type,
                        'started_at' => $session->started_at?->toIso8601String(),
                        'section_name' => $session->section?->name,
                        'status' => $record?->status ?? 'no_record',
                        'record' => $record ? [
                            'id' => $record->id,
                            'checked_in_at' => $record->checked_in_at?->toIso8601String(),
                            'can_submit_excuse' => $record->status === 'absent' && ! $record->excuse && ! $session->isLocked(),
                            'excuse' => $record->excuse ? StudentPresenter::excuse($record->excuse) : null,
                        ] : null,
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * How stale a scan queued offline may be before we stop trusting it. The
     * rotating code normally expires in well under a minute; this is the only
     * place that window is widened, so it stays short and is paired with the
     * session-window check below.
     */
    private const OFFLINE_GRACE_MINUTES = 120;

    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'payload' => ['required', 'string'],
            'scanned_at' => ['nullable', 'date'],
        ]);

        $parsed = $this->qrService->parsePayload($request->payload);

        if (! $parsed) {
            return $this->failure('Invalid QR code.');
        }

        $session = AttendanceSession::with('section.course')->find($parsed['session_id']);

        if (! $session) {
            return $this->failure('This attendance session has ended.');
        }

        if ($session->isLocked()) {
            return $this->failure('This attendance session is closed.');
        }

        // A scan queued while the phone had no signal carries the instant it was
        // taken. Students typically reconnect on the way out, after the lecturer
        // has ended the session, so a queued scan is judged against the session's
        // window rather than against "is it still running right now".
        $scannedAt = $request->date('scanned_at');
        $queued = $scannedAt !== null;
        $at = $scannedAt ?? now();

        if ($queued) {
            if ($at->isFuture() || $at->lt(now()->subMinutes(self::OFFLINE_GRACE_MINUTES))) {
                return $this->failure('This check-in is too old to submit. Ask your lecturer to mark you manually.');
            }

            if ($at->lt($session->started_at) || $at->gt($session->ended_at ?? now())) {
                return $this->failure('That scan was taken outside this session.');
            }
        } elseif (! $session->isActive()) {
            return $this->failure('This attendance session has ended.');
        }

        // Verify unless the session is explicitly fixed-code. `qr_mode` is a plain
        // string column, so testing for 'rotating' let any other value through
        // unverified.
        if ($session->qr_mode !== 'fixed') {
            $valid = $this->qrService->validateToken(
                (string) $parsed['token'],
                $session->qr_secret,
                $session->qr_rotation_seconds,
                $queued ? $at->getTimestamp() : null,
            );

            if (! $valid) {
                return $this->failure('QR code has expired. Please scan the latest code.');
            }
        }

        $user = $request->user();

        $enrolled = SectionStudent::where('section_id', $session->section_id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $enrolled) {
            return $this->failure('You are not enrolled in this section.');
        }

        $existing = AttendanceRecord::where('attendance_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        // Ending a session marks every no-show absent, so a queued scan that lands
        // afterwards finds that sweep's row waiting for it. Upgrade that one, but
        // never a status a lecturer set deliberately and never an excused record.
        $fromAbsentSweep = $existing
            && $queued
            && $existing->status === 'absent'
            && $existing->method === 'manual'
            && $existing->override_by === null
            && ! $existing->excuse()->exists();

        if ($existing && ! $fromAbsentSweep) {
            $message = match ($existing->status) {
                'absent' => 'Your lecturer has marked you absent for this session.',
                'excused' => 'You are excused from this session.',
                default => 'You have already checked in.',
            };

            return $this->checkInResponse($message, $existing, $session);
        }

        $minutesSinceStart = $session->started_at->diffInMinutes($at);
        $status = $minutesSinceStart > $session->late_threshold_minutes ? 'late' : 'present';

        $attributes = [
            'status' => $status,
            'checked_in_at' => $at,
            'method' => $queued ? 'qr_queued' : 'qr_scan',
            'device_info' => [
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 200),
                'queued_seconds' => $queued ? (int) $at->diffInSeconds(now()) : null,
            ],
        ];

        $record = $existing
            ? tap($existing)->update($attributes)
            : AttendanceRecord::create($attributes + [
                'attendance_session_id' => $session->id,
                'user_id' => $user->id,
            ]);

        return $this->checkInResponse(
            $status === 'late' ? 'Checked in (late).' : 'Checked in successfully!',
            $record,
            $session,
        );
    }

    public function submitExcuse(Request $request, AttendanceRecord $record): JsonResponse
    {
        if (! $record->session) {
            abort(404);
        }

        $user = $request->user();

        if ($record->user_id !== $user->id) {
            abort(403, 'This attendance record does not belong to you.');
        }

        if ($record->status !== 'absent') {
            return $this->failure('You can only submit excuses for absent records.');
        }

        if ($record->excuse) {
            return $this->failure('An excuse has already been submitted for this session.');
        }

        if ($record->session->isLocked()) {
            return $this->failure('This session is closed, so excuses can no longer be submitted. Contact your lecturer.');
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'in:medical,family_emergency,academic_conflict,official_duty,other'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $excuse = $this->excuseService->submit(
            $record,
            $user,
            $request->only(['reason', 'category']),
            $request->file('attachment'),
        );

        return response()->json([
            'message' => 'Your excuse has been submitted for review.',
            'data' => ['excuse' => StudentPresenter::excuse($excuse)],
        ], 201);
    }

    private function summary(array $summary, ?AttendancePolicy $policy): array
    {
        $level = $summary['warning_level'] !== null ? (int) $summary['warning_level'] : null;

        $threshold = $level && $policy?->warning_thresholds
            ? collect($policy->warning_thresholds)->firstWhere('level', $level)
            : null;

        return [
            'present' => (int) $summary['present'],
            'late' => (int) $summary['late'],
            'absent' => (int) $summary['absent'],
            'excused' => (int) $summary['excused'],
            'total_sessions' => (int) $summary['total_sessions'],
            'absence_count' => (int) $summary['absence_count'],
            'attendance_rate' => (float) $summary['attendance_rate'],
            'warning' => $level ? [
                'level' => $level,
                'label' => $threshold['label'] ?? 'Attendance Warning Level '.$level,
            ] : null,
        ];
    }

    private function checkInResponse(string $message, AttendanceRecord $record, AttendanceSession $session): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => [
                'status' => $record->status,
                'checked_in_at' => $record->checked_in_at?->toIso8601String(),
                'session' => [
                    'id' => $session->id,
                    'course' => StudentPresenter::course($session->section?->course),
                    'section_name' => $session->section?->name,
                ],
            ],
        ]);
    }

    private function failure(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422);
    }
}
