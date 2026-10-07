<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Lecturer\SectionDetailResource;
use App\Http\Resources\Api\V1\Lecturer\SectionResource;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Attendance\AttendanceSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    use AuthorizesLecturerAccess;

    public function show(Course $course, Section $section): SectionDetailResource
    {
        $this->ensureLecturer();
        $this->authorizeSection($course, $section);

        $section->load(['course:id,code,title', 'academicTerm', 'lecturers', 'activeStudents']);
        $section->setAttribute('active_session_id', $this->activeSessionId($section));

        return (new SectionDetailResource($section))
            ->withStudentIdNumbers($this->studentIdNumbers($section->activeStudents->pluck('id'))->all());
    }

    /**
     * Course owner or admin. Co-lecturers must be staff of this institution.
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        if (! $this->isCourseOwner($course)) {
            abort(403, 'Only the course owner can add sections.');
        }

        $validated = $request->validate($this->sectionRules());

        $section = Section::create([
            'tenant_id' => app('current_tenant')->id,
            'course_id' => $course->id,
            'academic_term_id' => $validated['academic_term_id'] ?? $course->academic_term_id,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'capacity' => $validated['capacity'] ?? null,
            'is_active' => true,
        ]);

        if (array_key_exists('lecturer_ids', $validated)) {
            $section->lecturers()->sync($validated['lecturer_ids'] ?? []);
        }

        return $this->sectionResponse($section, "Section '{$section->name}' created.", 201);
    }

    /**
     * Unlike the web, co-lecturers are only replaced when `lecturer_ids` is sent, and only
     * the course owner may change them.
     */
    public function update(Request $request, Course $course, Section $section): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSection($course, $section);

        $rules = collect($this->sectionRules())
            ->map(fn (array $rule) => array_map(fn ($r) => $r === 'required' ? 'sometimes' : $r, $rule))
            ->all();
        $validated = $request->validate($rules);

        if (array_key_exists('lecturer_ids', $validated) && ! $this->isCourseOwner($course)) {
            abort(403, 'Only the course owner can change section lecturers.');
        }

        $section->update(collect($validated)->only(['name', 'code', 'capacity', 'academic_term_id'])->all());

        if (array_key_exists('lecturer_ids', $validated)) {
            $section->lecturers()->sync($validated['lecturer_ids'] ?? []);
        }

        return $this->sectionResponse($section, 'Section updated.');
    }

    /**
     * Replaces the whole timetable; an empty list clears it.
     */
    public function updateSchedule(Request $request, Course $course, Section $section): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSection($course, $section);

        $validated = $request->validate([
            'schedule' => ['present', 'array', 'max:10'],
            'schedule.*.day' => ['required', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'schedule.*.start_time' => ['required', 'date_format:H:i'],
            'schedule.*.end_time' => ['required', 'date_format:H:i', 'after:schedule.*.start_time'],
            'schedule.*.location' => ['nullable', 'string', 'max:100'],
            'schedule.*.type' => ['required', 'string', 'in:lecture,tutorial,lab,other'],
        ]);

        $slots = collect($validated['schedule'])->map(fn (array $slot) => [
            'day' => $slot['day'],
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'location' => $slot['location'] ?? null,
            'type' => $slot['type'],
        ])->values()->all();

        $section->update(['schedule' => $slots === [] ? null : $slots]);

        return $this->sectionResponse($section, 'Timetable saved.');
    }

    /**
     * The web's CSV import (name, email, optional student ID columns, by any of the same
     * header aliases), reporting each skipped row and re-activating removed students.
     */
    public function importCsv(Request $request, Course $course, Section $section): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSection($course, $section);

        $request->validate(['csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $lines = file($request->file('csv_file')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $rows = array_map('str_getcsv', $lines);
        $header = array_map(
            fn ($cell) => strtolower(trim((string) preg_replace('/^\x{FEFF}/u', '', (string) $cell))),
            array_shift($rows) ?? [],
        );

        $nameCol = $this->findColumn($header, ['name', 'student_name', 'full_name']);
        $emailCol = $this->findColumn($header, ['email', 'student_email', 'e-mail']);
        $idCol = $this->findColumn($header, ['student_id', 'id_number', 'matric', 'student_id_number']);

        if ($nameCol === null || $emailCol === null) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV needs "name" and "email" columns.']);
        }

        $tenantId = app('current_tenant')->id;
        $imported = 0;
        $reactivated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $name = trim((string) ($row[$nameCol] ?? ''));
            $email = strtolower(trim((string) ($row[$emailCol] ?? '')));
            $studentId = $idCol !== null ? (trim((string) ($row[$idCol] ?? '')) ?: null) : null;

            if ($name === '' || $email === '') {
                $errors[] = ['line' => $line, 'message' => 'Missing name or email.'];

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['line' => $line, 'message' => "\"{$email}\" is not a valid email."];

                continue;
            }

            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make(Str::random(16))]);

            TenantUser::firstOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $user->id, 'role' => 'student'],
                ['student_id_number' => $studentId, 'is_active' => true, 'joined_at' => now()]
            );

            $enrollment = SectionStudent::where('section_id', $section->id)->where('user_id', $user->id)->first();

            if ($enrollment?->is_active) {
                $errors[] = ['line' => $line, 'message' => "{$user->name} is already enrolled."];

                continue;
            }

            if ($enrollment) {
                $enrollment->update(['is_active' => true]);
                $reactivated++;
            } else {
                SectionStudent::create([
                    'section_id' => $section->id,
                    'user_id' => $user->id,
                    'enrolled_at' => now(),
                    'enrollment_method' => 'csv',
                    'is_active' => true,
                ]);
                $imported++;
            }
        }

        $added = $imported + $reactivated;

        return response()->json([
            'message' => "{$added} ".($added === 1 ? 'student' : 'students').' added. '.count($errors).' skipped.',
            'data' => ['imported' => $imported, 'reactivated' => $reactivated, 'skipped' => count($errors), 'errors' => $errors],
        ]);
    }

    public function toggleActive(Course $course, Section $section, AttendanceSessionService $sessionService): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSection($course, $section);

        $section->update(['is_active' => ! $section->is_active]);

        $status = $section->is_active ? 'activated' : 'deactivated';
        $message = "Section '{$section->name}' {$status}.";

        // A deactivated section takes no attendance, so a running QR session stops here
        if (! $section->is_active && ($ended = $sessionService->endRunning($section)) > 0) {
            $message .= " Ended {$ended} running attendance ".Str::plural('session', $ended).'.';
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'id' => $section->id,
                'is_active' => (bool) $section->is_active,
            ],
        ]);
    }

    public function addStudent(Request $request, Course $course, Section $section): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSection($course, $section);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'student_id_number' => ['nullable', 'string', 'max:50'],
        ]);

        $tenant = app('current_tenant');

        $user = User::firstOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name,
                'password' => Hash::make(Str::random(16)),
            ]
        );

        $membership = TenantUser::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'student'],
            [
                'student_id_number' => $request->student_id_number,
                'is_active' => true,
                'joined_at' => now(),
            ]
        );

        $enrollment = SectionStudent::where('section_id', $section->id)
            ->where('user_id', $user->id)
            ->first();

        if ($enrollment?->is_active) {
            throw ValidationException::withMessages([
                'email' => "{$user->name} is already enrolled in this section.",
            ]);
        }

        // A previously removed student is re-activated instead of being blocked
        if ($enrollment) {
            $enrollment->update(['is_active' => true]);
        } else {
            $enrollment = SectionStudent::create([
                'section_id' => $section->id,
                'user_id' => $user->id,
                'enrolled_at' => now(),
                'enrollment_method' => 'manual',
                'is_active' => true,
            ]);
        }

        return response()->json([
            'message' => "{$user->name} added to {$section->name}.",
            'data' => [
                'student' => SectionDetailResource::rosterEntry(
                    $user,
                    $membership->student_id_number,
                    $enrollment->enrollment_method,
                    $enrollment->enrolled_at,
                ),
            ],
        ], 201);
    }

    public function removeStudent(Course $course, Section $section, User $user): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSection($course, $section);

        $removed = SectionStudent::where('section_id', $section->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        if ($removed === 0) {
            abort(404, 'This student is not enrolled in this section.');
        }

        return response()->json([
            'message' => 'Student removed from section.',
            'data' => [
                'section_id' => $section->id,
                'user_id' => $user->id,
            ],
        ]);
    }

    private function sectionRules(): array
    {
        $tenantId = app('current_tenant')->id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')->where('tenant_id', $tenantId)],
            'lecturer_ids' => ['nullable', 'array'],
            'lecturer_ids.*' => [
                'integer',
                Rule::exists('tenant_users', 'user_id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->whereIn('role', ['lecturer', 'admin', 'coordinator']),
            ],
        ];
    }

    private function sectionResponse(Section $section, string $message, int $status = 200): JsonResponse
    {
        $section->refresh()->load(['academicTerm', 'lecturers'])->loadCount('activeStudents');
        $section->setAttribute('active_session_id', $this->activeSessionId($section));

        return response()->json(['message' => $message, 'data' => (new SectionResource($section))->resolve()], $status);
    }

    /**
     * The section's running attendance session, never shown for an inactive section.
     */
    private function activeSessionId(Section $section): ?int
    {
        if (! $section->is_active) {
            return null;
        }

        return AttendanceSession::where('section_id', $section->id)->where('status', 'active')->value('id');
    }

    private function findColumn(array $header, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $index = array_search($alias, $header, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }
}
