<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

class SectionApiTest extends LecturerApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private Course $course;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'SKM3013']);
        $this->section = $this->createSection($this->course);
    }

    private function url(string $suffix = '', ?Section $section = null, ?Course $course = null): string
    {
        $course ??= $this->course;
        $section ??= $this->section;

        return $this->tenantApi($this->tenant, "lecturer/courses/{$course->id}/sections/{$section->id}".$suffix);
    }

    public function test_show_returns_the_roster_with_student_id_numbers(): void
    {
        $zul = $this->createMember($this->tenant, 'student', ['name' => 'Zul Hakim']);
        TenantUser::where('user_id', $zul->id)->update(['student_id_number' => 'A21EC0001']);
        $aina = $this->createMember($this->tenant, 'student', ['name' => 'Aina Sofea']);
        $removed = $this->createMember($this->tenant, 'student');
        $this->enroll($this->section, $zul);
        $this->enroll($this->section, $aina);
        $this->enroll($this->section, $removed);
        SectionStudent::where('user_id', $removed->id)->update(['is_active' => false]);

        $this->actingAsApi($this->lecturer)->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.id', $this->section->id)
            ->assertJsonPath('data.course.code', 'SKM3013')
            ->assertJsonPath('data.invite_code', $this->section->invite_code)
            ->assertJsonPath('data.active_students_count', 2)
            ->assertJsonCount(2, 'data.students')
            ->assertJsonPath('data.students.0.name', 'Aina Sofea')
            ->assertJsonPath('data.students.0.enrollment_method', 'manual')
            ->assertJsonPath('data.students.1.student_id_number', 'A21EC0001');
    }

    public function test_section_must_belong_to_the_course(): void
    {
        $otherCourse = $this->createCourse($this->tenant, $this->lecturer);

        $this->actingAsApi($this->lecturer)->getJson($this->url('', null, $otherCourse))
            ->assertNotFound();
    }

    public function test_section_lecturer_cannot_open_a_colleagues_section(): void
    {
        $colleague = $this->createMember($this->tenant, 'lecturer');
        $this->createSection($this->course, ['name' => 'Section 02', 'code' => '02'], [$colleague]);
        $foreignSection = $this->createSection($this->course, ['name' => 'Section 03', 'code' => '03'], [$this->createMember($this->tenant, 'lecturer')]);

        $this->actingAsApi($colleague)->getJson($this->url('', $foreignSection))
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have access to this section.');
    }

    public function test_toggle_active_flips_the_section(): void
    {
        $this->actingAsApi($this->lecturer)->postJson($this->url('/toggle-active'))
            ->assertOk()
            ->assertJsonPath('message', "Section 'Section 01' deactivated.")
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('sections', ['id' => $this->section->id, 'is_active' => false]);
    }

    public function test_add_student_creates_account_membership_and_enrolment(): void
    {
        $this->actingAsApi($this->lecturer)->postJson($this->url('/students'), [
            'name' => 'nur aina',
            'email' => 'aina@student.example.com',
            'student_id_number' => 'A22EC0100',
        ])
            ->assertCreated()
            ->assertJsonPath('data.student.name', 'Nur Aina')
            ->assertJsonPath('data.student.student_id_number', 'A22EC0100')
            ->assertJsonPath('data.student.enrollment_method', 'manual');

        $user = User::where('email', 'aina@student.example.com')->firstOrFail();
        $this->assertDatabaseHas('tenant_users', ['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'role' => 'student']);
        $this->assertDatabaseHas('section_students', ['section_id' => $this->section->id, 'user_id' => $user->id, 'is_active' => true]);
    }

    public function test_adding_an_enrolled_student_fails_validation(): void
    {
        $student = $this->createMember($this->tenant, 'student', ['email' => 'zul@student.example.com']);
        $this->enroll($this->section, $student);

        $this->actingAsApi($this->lecturer)->postJson($this->url('/students'), [
            'name' => $student->name,
            'email' => 'zul@student.example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_removed_student_can_be_added_again(): void
    {
        $student = $this->createMember($this->tenant, 'student', ['email' => 'zul@student.example.com']);
        $this->enroll($this->section, $student);

        $this->actingAsApi($this->lecturer)->deleteJson($this->url("/students/{$student->id}"))
            ->assertOk()
            ->assertJsonPath('message', 'Student removed from section.');
        $this->assertDatabaseHas('section_students', ['user_id' => $student->id, 'is_active' => false]);

        $this->postJson($this->url('/students'), ['name' => $student->name, 'email' => 'zul@student.example.com'])
            ->assertCreated();
        $this->assertDatabaseHas('section_students', ['user_id' => $student->id, 'is_active' => true]);
    }

    public function test_add_student_validates_input(): void
    {
        $this->actingAsApi($this->lecturer)->postJson($this->url('/students'), ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_removing_a_student_who_is_not_enrolled_is_not_found(): void
    {
        $student = $this->createMember($this->tenant, 'student');

        $this->actingAsApi($this->lecturer)->deleteJson($this->url("/students/{$student->id}"))
            ->assertNotFound();
    }

    public function test_student_role_is_rejected(): void
    {
        $student = $this->createMember($this->tenant, 'student');

        $this->actingAsApi($student)->getJson($this->url())->assertForbidden();
    }
}
