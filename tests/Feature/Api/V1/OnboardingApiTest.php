<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;

class OnboardingApiTest extends ApiTestCase
{
    public function test_guest_cannot_list_institutions(): void
    {
        $this->getJson('/api/v1/tenants')->assertUnauthorized();
    }

    public function test_lists_only_active_institutions(): void
    {
        $active = $this->createTenant(['name' => 'Alpha University']);
        $this->createTenant(['name' => 'Closed College', 'is_active' => false]);

        $this->actingAsApi(User::factory()->create())->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $active->slug);
    }

    public function test_student_joins_and_enrolls_with_a_section_invite_code(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer));
        $student = User::factory()->create();

        $this->actingAsApi($student)->postJson('/api/v1/onboarding', [
            'tenant_id' => $tenant->id,
            'role' => 'student',
            'invite_code' => strtolower($section->invite_code),
        ])
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', $tenant->slug)
            ->assertJsonPath('data.role', 'student');

        $this->assertDatabaseHas('tenant_users', ['tenant_id' => $tenant->id, 'user_id' => $student->id, 'role' => 'student']);
        $this->assertDatabaseHas('section_students', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'enrollment_method' => 'invite_code',
        ]);
    }

    public function test_course_code_entered_as_section_code_gets_a_helpful_error(): void
    {
        $tenant = $this->createTenant();
        $course = $this->createCourse($tenant, $this->createMember($tenant, 'lecturer'));

        $this->actingAsApi(User::factory()->create())->postJson('/api/v1/onboarding', [
            'tenant_id' => $tenant->id,
            'role' => 'student',
            'invite_code' => $course->invite_code,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('invite_code');

        $this->assertDatabaseCount('section_students', 0);
    }

    public function test_lecturer_can_create_a_new_institution(): void
    {
        $user = User::factory()->create();

        $this->actingAsApi($user)->postJson('/api/v1/onboarding', [
            'new_tenant_name' => 'Universiti Teknologi Malaysia',
            'role' => 'lecturer',
        ])
            ->assertCreated()
            ->assertJsonPath('data.tenant.slug', 'utm')
            ->assertJsonPath('data.role', 'lecturer');

        $this->assertDatabaseHas('tenant_users', ['user_id' => $user->id, 'role' => 'lecturer']);
    }

    public function test_requires_an_institution_choice(): void
    {
        $this->actingAsApi(User::factory()->create())->postJson('/api/v1/onboarding', ['role' => 'student'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tenant_id');
    }
}
