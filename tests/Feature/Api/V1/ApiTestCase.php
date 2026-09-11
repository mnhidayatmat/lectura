<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Base class for mobile API feature tests, with minimal Lectura fixtures.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createTenant(array $attributes = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Universiti '.Str::title(Str::lower(Str::random(6))),
            'slug' => 'u'.Str::lower(Str::random(8)),
            'timezone' => 'Asia/Kuala_Lumpur',
            'locale' => 'en',
            'is_active' => true,
        ], $attributes));
    }

    protected function createMember(Tenant $tenant, string $role = 'student', array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->addRole($tenant, $user, $role);

        return $user;
    }

    protected function addRole(Tenant $tenant, User $user, string $role): TenantUser
    {
        return TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    protected function createCourse(Tenant $tenant, User $lecturer, array $attributes = []): Course
    {
        return Course::create(array_merge([
            'tenant_id' => $tenant->id,
            'lecturer_id' => $lecturer->id,
            'code' => 'SKM'.random_int(1000, 9999),
            'title' => 'Engineering Mathematics',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * @param  array<int, User>  $lecturers  Section lecturers (section_lecturers pivot)
     */
    protected function createSection(Course $course, array $attributes = [], array $lecturers = []): Section
    {
        $section = Section::create(array_merge([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'name' => 'Section 01',
            'code' => '01',
            'is_active' => true,
        ], $attributes));

        if ($lecturers !== []) {
            $section->lecturers()->attach(collect($lecturers)->pluck('id')->all());
        }

        return $section;
    }

    protected function enroll(Section $section, User $student): void
    {
        $section->students()->attach($student->id, [
            'enrolled_at' => now(),
            'enrollment_method' => 'manual',
            'is_active' => true,
        ]);
    }

    /**
     * Authenticate as the user via Sanctum; optionally send the X-Lectura-Role header.
     */
    protected function actingAsApi(User $user, ?string $role = null): static
    {
        Sanctum::actingAs($user, ['*']);

        if ($role !== null) {
            $this->withHeader('X-Lectura-Role', $role);
        }

        return $this;
    }

    protected function tenantApi(Tenant $tenant, string $path = ''): string
    {
        return '/api/v1/t/'.$tenant->slug.'/'.ltrim($path, '/');
    }
}
