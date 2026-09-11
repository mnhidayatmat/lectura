<?php

namespace Tests\Feature\Api\V1\Workspace;

use App\Models\GroupTask;
use App\Models\StudentGroupPost;
use Tests\Feature\Api\V1\ApiTestCase;

class WorkspaceApiTest extends ApiTestCase
{
    use CreatesWorkspaceGroups;

    public function test_lists_my_groups_with_course_and_member_count(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'SKE3013']);
        $student = $this->createMember($tenant, 'student');
        $peer = $this->createMember($tenant, 'student');
        $group = $this->createGroup($course, [$student, $peer], $student);
        $this->createGroup($course, [$this->createMember($tenant, 'student')], null, 'Group B');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'workspace'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $group->id)
            ->assertJsonPath('data.0.member_count', 2)
            ->assertJsonPath('data.0.is_leader', true)
            ->assertJsonPath('data.0.course.code', 'SKE3013')
            ->assertJsonPath('data.0.group_set.type_label', 'Lecture');
    }

    public function test_show_returns_members_counts_and_permissions(): void
    {
        $tenant = $this->createTenant();
        $course = $this->createCourse($tenant, $this->createMember($tenant, 'lecturer'));
        $leader = $this->createMember($tenant, 'student');
        $member = $this->createMember($tenant, 'student');
        $group = $this->createGroup($course, [$leader, $member], $leader);

        StudentGroupPost::create(['student_group_id' => $group->id, 'user_id' => $member->id, 'body' => 'Hi']);
        GroupTask::create(['student_group_id' => $group->id, 'title' => 'Draft', 'status' => 'done', 'created_by' => $leader->id]);
        GroupTask::create(['student_group_id' => $group->id, 'title' => 'Slides', 'status' => 'todo', 'due_date' => now()->subDays(2), 'created_by' => $leader->id]);

        $this->actingAsApi($member)->getJson($this->tenantApi($tenant, "workspace/{$group->id}"))
            ->assertOk()
            ->assertJsonPath('data.is_leader', false)
            ->assertJsonPath('data.members.0.role', 'leader')
            ->assertJsonPath('data.counts.messages', 1)
            ->assertJsonPath('data.counts.tasks', 2)
            ->assertJsonPath('data.counts.tasks_done', 1)
            ->assertJsonPath('data.counts.tasks_overdue', 1)
            ->assertJsonPath('data.permissions.can_delete_any_file', false)
            ->assertJsonPath('data.score', null);
    }

    public function test_non_member_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $course = $this->createCourse($tenant, $this->createMember($tenant, 'lecturer'));
        $group = $this->createGroup($course, [$this->createMember($tenant, 'student')]);
        $outsider = $this->createMember($tenant, 'student');

        $this->actingAsApi($outsider)->getJson($this->tenantApi($tenant, "workspace/{$group->id}"))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not a member of this group.');
    }

    public function test_group_from_another_institution_is_not_found(): void
    {
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $otherTenant = $this->createTenant();
        $otherCourse = $this->createCourse($otherTenant, $this->createMember($otherTenant, 'lecturer'));
        $this->addRole($otherTenant, $student, 'student');
        $foreignGroup = $this->createGroup($otherCourse, [$student]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "workspace/{$foreignGroup->id}"))
            ->assertNotFound();
    }
}
