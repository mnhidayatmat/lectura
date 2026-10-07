<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\StudentGroupMember;

class GroupApiTest extends LecturerApiTestCase
{
    private function setUpSection(int $students = 5): array
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $section = $this->createSection($course);
        $roster = collect(range(1, $students))->map(function () use ($tenant, $section) {
            $student = $this->createMember($tenant, 'student');
            $this->enroll($section, $student);

            return $student;
        });

        return [$tenant, $lecturer, $course, $section, $roster];
    }

    public function test_random_sets_deal_every_student_and_rearranging_needs_confirmation(): void
    {
        [$tenant, $lecturer, $course, $section] = $this->setUpSection(5);
        $base = $this->tenantApi($tenant, "lecturer/courses/{$course->id}/group-sets");

        $set = $this->actingAsApi($lecturer)->postJson($base, [
            'name' => 'Lab groups', 'section_id' => $section->id, 'type' => 'lab',
            'creation_method' => 'random', 'group_size' => 2,
        ])->assertCreated()
            ->assertJsonCount(3, 'data.groups')
            ->assertJsonCount(0, 'data.unassigned')
            ->json('data.id');

        $this->actingAsApi($lecturer)->postJson("$base/$set/arrange-random", ['group_size' => 3])->assertStatus(409);
        $this->actingAsApi($lecturer)->postJson("$base/$set/arrange-random", ['group_size' => 3, 'replace' => true])
            ->assertOk()
            ->assertJsonCount(2, 'data.groups');

        $this->actingAsApi($lecturer)->getJson($base)->assertOk()->assertJsonPath('data.0.groups_count', 2);
    }

    public function test_manual_groups_members_moves_and_leaders(): void
    {
        [$tenant, $lecturer, $course, $section, $roster] = $this->setUpSection(3);
        $outsider = $this->createMember($tenant, 'student');
        $base = $this->tenantApi($tenant, "lecturer/courses/{$course->id}/group-sets");

        $set = $this->actingAsApi($lecturer)->postJson($base, [
            'name' => 'Projects', 'section_id' => $section->id, 'type' => 'lecture', 'creation_method' => 'manual',
        ])->assertCreated()->assertJsonCount(3, 'data.unassigned')->json('data.id');

        $a = $this->actingAsApi($lecturer)->postJson("$base/$set/groups", ['name' => 'Alpha'])->assertCreated()->json('data.groups.0.id');
        $b = $this->actingAsApi($lecturer)->postJson("$base/$set/groups", ['name' => 'Beta'])->assertCreated()->json('data.groups.1.id');

        [$first, $second] = [$roster[0], $roster[1]];
        $this->actingAsApi($lecturer)->postJson("$base/$set/groups/$a/members", ['user_id' => $outsider->id])
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->actingAsApi($lecturer)->postJson("$base/$set/groups/$a/members", ['user_id' => $first->id])->assertCreated();
        $this->actingAsApi($lecturer)->postJson("$base/$set/groups/$a/members", ['user_id' => $second->id])->assertCreated();
        $this->actingAsApi($lecturer)->postJson("$base/$set/groups/$b/members", ['user_id' => $first->id])
            ->assertStatus(422);

        $this->actingAsApi($lecturer)->postJson("$base/$set/groups/$a/leader", ['user_id' => $second->id])
            ->assertOk()
            ->assertJsonPath('data.groups.0.members.0.id', $second->id)
            ->assertJsonPath('data.groups.0.members.0.role', 'leader');

        // A moved leader arrives as a plain member.
        $this->actingAsApi($lecturer)->postJson("$base/$set/members/{$second->id}/move", ['group_id' => $b])
            ->assertOk()
            ->assertJsonPath('data.groups.1.members.0.id', $second->id)
            ->assertJsonPath('data.groups.1.members.0.role', 'member');

        $this->actingAsApi($lecturer)->patchJson("$base/$set/groups/$b", ['name' => 'Beta Team'])
            ->assertOk()->assertJsonPath('data.groups.1.name', 'Beta Team');

        $this->actingAsApi($lecturer)->deleteJson("$base/$set/groups/$b")->assertStatus(409);
        $this->actingAsApi($lecturer)->deleteJson("$base/$set/groups/$b", ['confirm' => true])
            ->assertOk()->assertJsonCount(1, 'data.groups');
        $this->assertFalse(StudentGroupMember::where('user_id', $second->id)->exists());
    }

    public function test_sets_are_limited_to_the_lecturers_own_sections(): void
    {
        [$tenant, $owner, $course, $section] = $this->setUpSection(1);
        $sectionLecturer = $this->createMember($tenant, 'lecturer');
        $this->createSection($course, ['name' => 'Section 02', 'code' => '02'], [$sectionLecturer]);
        $base = $this->tenantApi($tenant, "lecturer/courses/{$course->id}/group-sets");

        $set = $this->actingAsApi($owner)->postJson($base, [
            'name' => 'Owner set', 'section_id' => $section->id, 'type' => 'lab', 'creation_method' => 'manual',
        ])->json('data.id');

        $this->actingAsApi($sectionLecturer)->postJson($base, [
            'name' => 'Not mine', 'section_id' => $section->id, 'type' => 'lab', 'creation_method' => 'manual',
        ])->assertStatus(422)->assertJsonValidationErrors('section_id');

        $this->actingAsApi($sectionLecturer)->deleteJson("$base/$set")->assertForbidden();
        $this->actingAsApi($sectionLecturer)->getJson($base)->assertOk()->assertJsonCount(0, 'data');
    }
}
