<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\AttendanceExcuse;
use App\Services\Attendance\AttendanceExcuseService;
use App\Services\Attendance\AttendanceWarningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcuseController extends Controller
{
    use AuthorizesLecturerAccess;

    private const STATUSES = ['pending', 'approved', 'rejected', 'all'];

    public function __construct(
        protected AttendanceExcuseService $excuses,
        protected AttendanceWarningService $warnings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->ensureLecturer();

        $request->validate(['status' => ['nullable', 'in:'.implode(',', self::STATUSES)]]);
        $status = $request->query('status', 'pending');

        $inScope = fn () => AttendanceExcuse::whereHas(
            'record.session',
            fn ($q) => $q->whereIn('section_id', $this->allAccessibleSectionIds())
        );

        $excuses = $inScope()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['user', 'reviewer', 'record.session.section.course'])
            ->latest()
            ->paginate(20);

        $studentIds = $this->studentIdNumbers($excuses->getCollection()->pluck('user_id'));

        return response()->json([
            'data' => $excuses->getCollection()->map(fn (AttendanceExcuse $excuse) => $this->present($excuse, $studentIds[$excuse->user_id] ?? null)),
            'meta' => [
                'current_page' => $excuses->currentPage(),
                'last_page' => $excuses->lastPage(),
                'total' => $excuses->total(),
                'pending_count' => $inScope()->pending()->count(),
            ],
        ]);
    }

    public function approve(Request $request, AttendanceExcuse $excuse): JsonResponse
    {
        return $this->review($request, $excuse, approve: true);
    }

    public function reject(Request $request, AttendanceExcuse $excuse): JsonResponse
    {
        return $this->review($request, $excuse, approve: false);
    }

    public function attachment(AttendanceExcuse $excuse): StreamedResponse
    {
        $this->authorizeExcuse($excuse);

        if (! $excuse->attachment_path || ! Storage::disk('local')->exists($excuse->attachment_path)) {
            abort(404, 'This excuse has no attachment.');
        }

        return Storage::disk('local')->download($excuse->attachment_path, $excuse->attachment_filename);
    }

    private function review(Request $request, AttendanceExcuse $excuse, bool $approve): JsonResponse
    {
        $this->authorizeExcuse($excuse);

        $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        if ($excuse->status !== 'pending') {
            abort(422, 'This excuse has already been '.$excuse->status.'.');
        }

        if ($approve) {
            $this->excuses->approve($excuse, $request->user(), $request->input('note'));
            // One absence fewer: warnings may need to step down.
            $this->warnings->checkAndIssueWarnings($excuse->record->session->section->course);
        } else {
            $this->excuses->reject($excuse, $request->user(), $request->input('note'));
        }

        $excuse->refresh()->load(['user', 'reviewer', 'record.session.section.course']);

        return response()->json([
            'message' => $approve ? 'Excuse approved. The record is now excused.' : 'Excuse rejected.',
            'data' => $this->present($excuse, $this->studentIdNumbers([$excuse->user_id])[$excuse->user_id] ?? null),
        ]);
    }

    /**
     * The web only lets the course owner review; the API also admits section lecturers,
     * who can already set a record to excused through the attendance override.
     */
    private function authorizeExcuse(AttendanceExcuse $excuse): void
    {
        $this->ensureLecturer();

        $session = $excuse->record?->session;
        $course = $session?->section?->course;

        // Excuses carry no tenant scope of their own, so check it here.
        if (! $course || $course->tenant_id !== app('current_tenant')->id) {
            abort(404, 'Excuse not found.');
        }

        $this->authorizeSession($session);
    }

    private function present(AttendanceExcuse $excuse, ?string $studentIdNumber): array
    {
        $record = $excuse->record;
        $session = $record?->session;
        $section = $session?->section;
        $course = $section?->course;

        return [
            'id' => $excuse->id,
            'status' => $excuse->status,
            'category' => $excuse->category,
            'category_label' => ucfirst(str_replace('_', ' ', (string) $excuse->category)),
            'reason' => $excuse->reason,
            'has_attachment' => (bool) $excuse->attachment_path,
            'attachment_filename' => $excuse->attachment_filename,
            'submitted_at' => $excuse->created_at?->toIso8601String(),
            'reviewed_at' => $excuse->reviewed_at?->toIso8601String(),
            'reviewer_note' => $excuse->reviewer_note,
            'reviewer' => $excuse->reviewer ? ['id' => $excuse->reviewer->id, 'name' => $excuse->reviewer->name] : null,
            'student' => [
                'id' => $excuse->user?->id,
                'name' => $excuse->user?->name,
                'email' => $excuse->user?->email,
                'student_id_number' => $studentIdNumber,
            ],
            'record' => [
                'id' => $record?->id,
                'status' => $record?->status,
            ],
            'session' => [
                'id' => $session?->id,
                'session_type' => $session?->session_type,
                'week_number' => $session?->week_number,
                'started_at' => $session?->started_at?->toIso8601String(),
            ],
            'section' => $section ? ['id' => $section->id, 'name' => $section->name] : null,
            'course' => $course ? ['id' => $course->id, 'code' => $course->code, 'title' => $course->title] : null,
        ];
    }
}
