<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
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
        ]);

        $tenant = app('current_tenant');

        Section::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'name' => $request->name,
            'code' => $request->code,
            'capacity' => $request->capacity,
        ]);

        return back()->with('success', "Section '{$request->name}' created.");
    }

    public function show(string $tenantSlug, Course $course, Section $section): View
    {
        $section->load(['activeStudents', 'course']);

        return view('tenant.courses.sections.show', compact('course', 'section'));
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
