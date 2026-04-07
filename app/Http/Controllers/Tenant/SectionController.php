<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'academic_term_id' => ['nullable', 'exists:academic_terms,id'],
            'lecturer_ids' => ['nullable', 'array'],
            'lecturer_ids.*' => ['exists:users,id'],
        ]);

        $tenant = app('current_tenant');

        $section = Section::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'academic_term_id' => $request->academic_term_id,
            'name' => $request->name,
            'code' => $request->code,
            'capacity' => $request->capacity,
        ]);

        if ($request->filled('lecturer_ids')) {
            $section->lecturers()->sync($request->lecturer_ids);
        }

        return back()->with('success', "Section '{$request->name}' created.");
    }

    public function show(string $tenantSlug, Course $course, Section $section): View
    {
        $section->load(['activeStudents', 'course', 'academicTerm', 'lecturers']);
        $terms = AcademicTerm::orderByDesc('start_date')->get();

        $tenant = app('current_tenant');
        $lecturers = \App\Models\TenantUser::where('tenant_id', $tenant->id)
            ->whereIn('role', ['lecturer', 'admin', 'coordinator'])
            ->where('is_active', true)
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($tu) => $tu->user)
            ->filter()
            ->sortBy('name')
            ->values();

        return view('tenant.courses.sections.show', compact('course', 'section', 'terms', 'lecturers'));
    }

    public function update(Request $request, string $tenantSlug, Course $course, Section $section): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'academic_term_id' => ['nullable', 'exists:academic_terms,id'],
            'lecturer_ids' => ['nullable', 'array'],
            'lecturer_ids.*' => ['exists:users,id'],
        ]);

        $section->update($request->only('name', 'code', 'capacity', 'academic_term_id'));
        $section->lecturers()->sync($request->input('lecturer_ids', []));

        return back()->with('success', 'Section details updated.');
    }

    public function toggleActive(string $tenantSlug, Course $course, Section $section): RedirectResponse
    {
        $section->update(['is_active' => ! $section->is_active]);

        $status = $section->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Section '{$section->name}' {$status}.");
    }

    public function addStudent(Request $request, string $tenantSlug, Course $course, Section $section): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'student_id_number' => ['nullable', 'string', 'max:50'],
        ]);

        $tenant = app('current_tenant');

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name,
                'password' => Hash::make(Str::random(16)),
            ]
        );

        // Ensure user is a student in tenant
        TenantUser::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'student'],
            [
                'student_id_number' => $request->student_id_number,
                'is_active' => true,
                'joined_at' => now(),
            ]
        );

        // Enroll in section
        $existing = SectionStudent::where('section_id', $section->id)
            ->where('user_id', $user->id)->first();

        if ($existing) {
            return back()->with('error', "{$user->name} is already enrolled in this section.");
        }

        SectionStudent::create([
            'section_id' => $section->id,
            'user_id' => $user->id,
            'enrolled_at' => now(),
            'enrollment_method' => 'manual',
            'is_active' => true,
        ]);

        return back()->with('success', "{$user->name} added to {$section->name}.");
    }

    public function importCsv(Request $request, string $tenantSlug, Course $course, Section $section): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $tenant = app('current_tenant');
        $file = $request->file('csv_file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('strtolower', array_map('trim', array_shift($rows)));

        $nameCol = $this->findColumn($header, ['name', 'student_name', 'full_name']);
        $emailCol = $this->findColumn($header, ['email', 'student_email', 'e-mail']);
        $idCol = $this->findColumn($header, ['student_id', 'id_number', 'matric', 'student_id_number']);

        if ($nameCol === null || $emailCol === null) {
            return back()->with('error', 'CSV must have "name" and "email" columns.');
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (count($row) <= max($nameCol, $emailCol)) {
                continue;
            }

            $name = trim($row[$nameCol]);
            $email = trim($row[$emailCol]);
            $studentId = $idCol !== null && isset($row[$idCol]) ? trim($row[$idCol]) : null;

            if (empty($name) || empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make(Str::random(16))]
            );

            TenantUser::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'student'],
                ['student_id_number' => $studentId, 'is_active' => true, 'joined_at' => now()]
            );

            $exists = SectionStudent::where('section_id', $section->id)
                ->where('user_id', $user->id)->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            SectionStudent::create([
                'section_id' => $section->id,
                'user_id' => $user->id,
                'enrolled_at' => now(),
                'enrollment_method' => 'csv',
                'is_active' => true,
            ]);

            $imported++;
        }

        return back()->with('success', "{$imported} students imported. {$skipped} skipped.");
    }

    public function removeStudent(string $tenantSlug, Course $course, Section $section, int $userId): RedirectResponse
    {
        SectionStudent::where('section_id', $section->id)
            ->where('user_id', $userId)
            ->update(['is_active' => false]);

        return back()->with('success', 'Student removed from section.');
    }

    public function updateSchedule(Request $request, string $tenantSlug, Course $course, Section $section): RedirectResponse
    {
        $request->validate([
            'schedule' => ['nullable', 'array', 'max:10'],
            'schedule.*.day' => ['required', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'schedule.*.start_time' => ['required', 'date_format:H:i'],
            'schedule.*.end_time' => ['required', 'date_format:H:i', 'after:schedule.*.start_time'],
            'schedule.*.location' => ['nullable', 'string', 'max:100'],
            'schedule.*.type' => ['required', 'string', 'in:lecture,tutorial,lab,other'],
        ]);

        $schedule = collect($request->input('schedule', []))
            ->filter(fn ($slot) => !empty($slot['day']) && !empty($slot['start_time']) && !empty($slot['end_time']))
            ->values()
            ->toArray();

        $section->update(['schedule' => $schedule ?: null]);

        return back()->with('success', __('Schedule updated successfully.'));
    }

    protected function findColumn(array $header, array $names): ?int
    {
        foreach ($names as $name) {
            $index = array_search($name, $header);
            if ($index !== false) {
                return (int) $index;
            }
        }
        return null;
    }
}
