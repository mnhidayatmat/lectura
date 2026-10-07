<?php

namespace Tests\Feature\Api\V1\Lecturer;

class CourseApiTest extends LecturerApiTestCase
{
    public function test_index_lists_owned_and_section_assigned_courses_only(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $colleague = $this->createMember($tenant, 'lecturer');
        $owned = $this->createCourse($tenant, $lecturer, ['code' => 'SKM1001']);
        $assigned = $this->createCourse($tenant, $colleague, ['code' => 'SKM2002']);
        $this->createSection($assigned, [], [$lecturer]);
        $this->createCourse($tenant, $colleague, ['code' => 'SKM3003']);

        $response = $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/courses'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [[
                'id', 'code', 'title', 'status', 'status_label', 'status_color',
                'teaching_mode', 'num_weeks', 'credit_hours', 'sections_count', 'academic_term', 'faculty',
            ]]]);

        $this->assertEqualsCanonicalizing([$owned->id, $assigned->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_index_orders_courses_by_semester_with_the_newest_first(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $older = $this->createTerm($tenant, [
            'name' => 'Semester 2, 2025/2026',
            'start_date' => '2026-02-01',
            'end_date' => '2026-06-30',
        ]);
        $newer = $this->createTerm($tenant, [
            'name' => 'Semester 1, 2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-31',
        ]);

        // Created oldest-first on purpose: a plain ->latest() ordering would invert this.
        $past = $this->createCourse($tenant, $lecturer, ['code' => 'SKM1001', 'academic_term_id' => $older->id]);
        $current = $this->createCourse($tenant, $lecturer, ['code' => 'SKM2002', 'academic_term_id' => $newer->id]);
        $unassigned = $this->createCourse($tenant, $lecturer, ['code' => 'SKM3003']);

        $ids = collect(
            $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/courses'))
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        // The app groups by first appearance because the payload carries no term
        // dates, so this order is load-bearing, not cosmetic.
        $this->assertSame([$current->id, $past->id, $unassigned->id], $ids);
    }

    public function test_index_keeps_archived_courses_and_labels_them(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $active = $this->createCourse($tenant, $lecturer, ['code' => 'SKM1001']);
        $archived = $this->createCourse($tenant, $lecturer, ['code' => 'SKM2002', 'status' => 'archived']);

        $data = collect(
            $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/courses'))
                ->assertOk()
                ->json('data')
        );

        // The app folds archived courses away itself; filtering them out here would
        // make a closed semester unreachable on mobile.
        $this->assertEqualsCanonicalizing([$active->id, $archived->id], $data->pluck('id')->all());
        $this->assertSame('archived', $data->firstWhere('id', $archived->id)['status']);
    }

    public function test_an_active_course_whose_semester_has_ended_is_labelled_ended(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $over = $this->createTerm($tenant, ['start_date' => '2025-02-01', 'end_date' => '2025-06-30']);
        $running = $this->createTerm($tenant, ['start_date' => '2025-02-01', 'end_date' => now()->addMonth()->toDateString()]);

        $ended = $this->createCourse($tenant, $lecturer, ['code' => 'SKM1001', 'academic_term_id' => $over->id]);
        $live = $this->createCourse($tenant, $lecturer, ['code' => 'SKM2002', 'academic_term_id' => $running->id]);
        $none = $this->createCourse($tenant, $lecturer, ['code' => 'SKM3003']);

        $data = collect(
            $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/courses'))
                ->assertOk()
                ->json('data')
        );

        $this->assertSame('Ended', $data->firstWhere('id', $ended->id)['status_label']);
        $this->assertSame('active', $data->firstWhere('id', $ended->id)['status']);
        $this->assertTrue($data->firstWhere('id', $ended->id)['term_ended']);
        $this->assertSame('Active', $data->firstWhere('id', $live->id)['status_label']);
        // No semester and no sections: not running this semester
        $this->assertSame('Inactive', $data->firstWhere('id', $none->id)['status_label']);
    }

    public function test_an_active_course_without_an_active_section_this_semester_is_labelled_inactive(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $current = $this->createTerm($tenant, ['start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonth()->toDateString()]);
        $next = $this->createTerm($tenant, ['start_date' => now()->addMonths(2)->toDateString(), 'end_date' => now()->addMonths(6)->toDateString()]);

        // Course has no semester; its section is in the current one
        $running = $this->createCourse($tenant, $lecturer, ['code' => 'BTG3333']);
        $this->createSection($running, ['academic_term_id' => $current->id]);

        // Only section is switched off
        $switchedOff = $this->createCourse($tenant, $lecturer, ['code' => 'BTD2232']);
        $this->createSection($switchedOff, ['academic_term_id' => $current->id, 'is_active' => false]);

        // Section sits in another semester
        $elsewhere = $this->createCourse($tenant, $lecturer, ['code' => 'BTG2663']);
        $this->createSection($elsewhere, ['academic_term_id' => $next->id]);

        // Section inherits the course's current semester
        $inherits = $this->createCourse($tenant, $lecturer, ['code' => 'BTD4122', 'academic_term_id' => $current->id]);
        $this->createSection($inherits);

        $data = collect(
            $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/courses'))
                ->assertOk()
                ->json('data')
        );

        $this->assertSame('Active', $data->firstWhere('id', $running->id)['status_label']);
        $this->assertFalse($data->firstWhere('id', $running->id)['not_running']);
        $this->assertSame('Active', $data->firstWhere('id', $inherits->id)['status_label']);
        foreach ([$switchedOff, $elsewhere] as $course) {
            $row = $data->firstWhere('id', $course->id);
            $this->assertSame('Inactive', $row['status_label']);
            $this->assertSame('red', $row['status_color']);
            $this->assertSame('active', $row['status']);
            $this->assertTrue($row['not_running']);
        }
    }

    public function test_owner_sees_all_sections_with_counts_and_the_course_invite_code(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $colleague = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner);
        $mine = $this->createSection($course, ['name' => 'Section 01']);
        $theirs = $this->createSection($course, ['name' => 'Section 02', 'code' => '02'], [$colleague]);
        $this->enroll($mine, $this->createMember($tenant));
        $session = $this->startAttendance($mine, $owner);

        $this->actingAsApi($owner)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}"))
            ->assertOk()
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.invite_code', $course->invite_code)
            ->assertJsonPath('data.total_students', 1)
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonPath('data.sections.0.id', $mine->id)
            ->assertJsonPath('data.sections.0.invite_code', $mine->invite_code)
            ->assertJsonPath('data.sections.0.active_students_count', 1)
            ->assertJsonPath('data.sections.0.active_session_id', $session->id)
            ->assertJsonPath('data.sections.1.id', $theirs->id)
            ->assertJsonPath('data.sections.1.lecturers.0.id', $colleague->id)
            ->assertJsonPath('data.sections.1.active_session_id', null);
    }

    public function test_an_inactive_section_stays_listed_without_its_live_session(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner);
        $inactive = $this->createSection($course, ['is_active' => false]);
        $this->startAttendance($inactive, $owner);

        $this->actingAsApi($owner)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}"))
            ->assertOk()
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.sections.0.id', $inactive->id)
            ->assertJsonPath('data.sections.0.is_active', false)
            ->assertJsonPath('data.sections.0.active_session_id', null);
    }

    public function test_section_lecturer_sees_only_assigned_sections_without_the_course_invite_code(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $colleague = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner);
        $this->createSection($course, ['name' => 'Section 01']);
        $theirs = $this->createSection($course, ['name' => 'Section 02', 'code' => '02'], [$colleague]);

        $this->actingAsApi($colleague)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}"))
            ->assertOk()
            ->assertJsonPath('data.is_owner', false)
            ->assertJsonPath('data.invite_code', null)
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.sections.0.id', $theirs->id);
    }

    public function test_unrelated_lecturer_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $course = $this->createCourse($tenant, $this->createMember($tenant, 'lecturer'));
        $outsider = $this->createMember($tenant, 'lecturer');

        $this->actingAsApi($outsider)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}"))
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have access to this course.');
    }

    public function test_course_from_another_tenant_is_not_found(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $otherTenant = $this->createTenant();
        $foreign = $this->createCourse($otherTenant, $this->createMember($otherTenant, 'lecturer'));

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, "lecturer/courses/{$foreign->id}"))
            ->assertNotFound();
    }

    public function test_student_role_is_rejected(): void
    {
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'lecturer/courses'))
            ->assertForbidden()
            ->assertJsonPath('message', 'This area is for lecturers.');
    }

    public function test_a_lecturer_takes_over_a_course_by_its_invite_code(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $lecturer = $this->createMember($tenant, 'lecturer');
        $student = $this->createMember($tenant, 'student');
        $course = $this->createCourse($tenant, $owner, ['invite_code' => 'SKM1001X']);
        $path = $this->tenantApi($tenant, 'lecturer/courses/join');

        $this->actingAsApi($lecturer)->postJson($path, ['invite_code' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('invite_code');

        $this->actingAsApi($student)->postJson($path, ['invite_code' => 'SKM1001X'])->assertForbidden();

        // Typed loosely on a phone keyboard: lowercase with a stray dash.
        $this->actingAsApi($lecturer)->postJson($path, ['invite_code' => 'skm1001-x'])
            ->assertOk()
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.already_joined', false);

        $this->assertSame($lecturer->id, $course->fresh()->lecturer_id);

        $this->actingAsApi($lecturer)->postJson($path, ['invite_code' => 'SKM1001X'])
            ->assertOk()
            ->assertJsonPath('data.already_joined', true);
    }
}
