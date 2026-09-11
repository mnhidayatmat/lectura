<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Lecturer\SectionDetailResource;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    use AuthorizesLecturerAccess;

    public function show(Course $course, Section $section): SectionDetailResource
    {
        $this->ensureLecturer();
        $this->authorizeSection($course, $section);

        $section->load(['course:id,code,title', 'academicTerm', 'lecturers', 'activeStudents']);
        $section->setAttribute(
            'active_session_id',
            AttendanceSession::where('section_id', $section->id)->where('status', 'active')->value('id')
        );

        return (new SectionDetailResource($section))
            ->withStudentIdNumbers($this->studentIdNumbers($section->activeStudents->pluck('id'))->all());
    }

    public function toggleActive(Course $course, Section $section): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeSection($course, $section);

        $section->update(['is_active' => ! $section->is_active]);

        $status = $section->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "Section '{$section->name}' {$status}.",
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
}
