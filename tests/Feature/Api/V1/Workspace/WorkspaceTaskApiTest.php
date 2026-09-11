<?php

namespace Tests\Feature\Api\V1\Workspace;

use App\Models\Course;
use App\Models\GroupTask;
use App\Models\StudentGroup;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\Api\V1\ApiTestCase;

class WorkspaceTaskApiTest extends ApiTestCase
{
    use CreatesWorkspaceGroups;

    private Tenant $tenant;

    private Course $course;

    private User $leader;

    private User $member;

    private StudentGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->course = $this->createCourse($this->tenant, $this->createMember($this->tenant, 'lecturer'));
        $this->leader = $this->createMember($this->tenant, 'student');
        $this->member = $this->createMember($this->tenant, 'student');
        $this->group = $this->createGroup($this->course, [$this->leader, $this->member], $this->leader);
    }

    public function test_member_can_create_and_list_tasks(): void
    {
        $this->actingAsApi($this->member)->postJson($this->tasksUrl(), [
            'title' => 'Write introduction',
            'description' => "  Cover the pump theory.\nInclude references.  ",
            'assigned_to' => $this->leader->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Write introduction')
            ->assertJsonPath('data.description', "Cover the pump theory.\nInclude references.")
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.assignee.id', $this->leader->id)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', true);

        $this->getJson($this->tasksUrl())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', "Cover the pump theory.\nInclude references.")
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.todo', 1);
    }

    public function test_task_validation_on_create(): void
    {
        $outsider = $this->createMember($this->tenant, 'student');

        $this->actingAsApi($this->member)->postJson($this->tasksUrl(), [
            'title' => '',
            'assigned_to' => $outsider->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'assigned_to', 'due_date']);

        $this->postJson($this->tasksUrl(), ['title' => 'Long one', 'description' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_creator_can_edit_every_field(): void
    {
        $task = $this->makeTask($this->member, ['assigned_to' => $this->member->id, 'due_date' => now()->addDays(5)]);

        $this->actingAsApi($this->member)->patchJson($this->tasksUrl()."/{$task->id}", [
            'title' => 'Collect field data',
            'description' => 'Bring the flow meter.',
            'assigned_to' => $this->leader->id,
            'start_date' => now()->addDay()->toDateString(),
            'due_date' => now()->addDays(6)->toDateString(),
            'status' => 'in_progress',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Task updated.')
            ->assertJsonPath('data.title', 'Collect field data')
            ->assertJsonPath('data.description', 'Bring the flow meter.')
            ->assertJsonPath('data.assignee.id', $this->leader->id)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.start_date', now()->addDay()->toDateString())
            ->assertJsonPath('data.due_date', now()->addDays(6)->toDateString());
    }

    public function test_nulls_clear_the_description_assignee_and_dates(): void
    {
        $task = $this->makeTask($this->leader, [
            'description' => 'Old notes',
            'assigned_to' => $this->member->id,
            'start_date' => now(),
            'due_date' => now()->addDay(),
        ]);

        $this->actingAsApi($this->leader)->patchJson($this->tasksUrl()."/{$task->id}", [
            'description' => null,
            'assigned_to' => null,
            'start_date' => null,
            'due_date' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.assignee', null)
            ->assertJsonPath('data.start_date', null)
            ->assertJsonPath('data.due_date', null);
    }

    public function test_any_member_can_change_status_but_only_creator_or_leader_can_edit_content(): void
    {
        $task = $this->makeTask($this->leader);

        $this->actingAsApi($this->member)->patchJson($this->tasksUrl()."/{$task->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.can_edit', false);

        $this->patchJson($this->tasksUrl()."/{$task->id}", ['title' => 'Hijacked'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the task creator or group leader can edit tasks.');

        // The leader may edit anyone's task
        $memberTask = $this->makeTask($this->member);
        $this->actingAsApi($this->leader)
            ->patchJson($this->tasksUrl()."/{$memberTask->id}", ['description' => 'Leader note'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Leader note');
    }

    public function test_update_validation(): void
    {
        $outsider = $this->createMember($this->tenant, 'student');
        $task = $this->makeTask($this->member, ['start_date' => now()->addDays(3)]);
        $url = $this->tasksUrl()."/{$task->id}";

        $this->actingAsApi($this->member)->patchJson($url, ['assigned_to' => $outsider->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('assigned_to');

        // Due date is compared against the date already stored on the task
        $this->patchJson($url, ['due_date' => now()->addDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('due_date');

        $this->patchJson($url, ['title' => ''])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->patchJson($url, ['status' => 'archived'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->patchJson($url, [])->assertStatus(422)->assertJsonValidationErrors('title');
    }

    public function test_only_creator_or_leader_can_delete(): void
    {
        $task = $this->makeTask($this->leader);

        $this->actingAsApi($this->member)->deleteJson($this->tasksUrl()."/{$task->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the task creator or group leader can delete tasks.');

        $memberTask = $this->makeTask($this->member);

        $this->actingAsApi($this->leader)->deleteJson($this->tasksUrl()."/{$memberTask->id}")->assertOk();
        $this->assertDatabaseMissing('group_tasks', ['id' => $memberTask->id]);
    }

    public function test_task_from_another_group_is_forbidden(): void
    {
        $otherGroup = $this->createGroup($this->course, [$this->createMember($this->tenant, 'student')], null, 'Group B');
        $foreignTask = GroupTask::create([
            'student_group_id' => $otherGroup->id,
            'title' => 'Not yours',
            'status' => 'todo',
            'created_by' => $otherGroup->members()->first()->user_id,
        ]);

        $this->actingAsApi($this->member)
            ->patchJson($this->tasksUrl()."/{$foreignTask->id}", ['status' => 'done'])
            ->assertForbidden();
    }

    public function test_tasks_of_a_group_in_another_institution_are_not_found(): void
    {
        $otherTenant = $this->createTenant();
        $otherCourse = $this->createCourse($otherTenant, $this->createMember($otherTenant, 'lecturer'));
        $this->addRole($otherTenant, $this->member, 'student');
        $foreignGroup = $this->createGroup($otherCourse, [$this->member]);
        $foreignTask = GroupTask::create([
            'student_group_id' => $foreignGroup->id,
            'title' => 'Other institution',
            'status' => 'todo',
            'created_by' => $this->member->id,
        ]);

        $url = $this->tenantApi($this->tenant, "workspace/{$foreignGroup->id}/tasks");

        $this->actingAsApi($this->member)->getJson($url)->assertNotFound();
        $this->patchJson("{$url}/{$foreignTask->id}", ['status' => 'done'])->assertNotFound();
    }

    private function makeTask(User $creator, array $attributes = []): GroupTask
    {
        return GroupTask::create(array_merge([
            'student_group_id' => $this->group->id,
            'title' => 'Collect data',
            'status' => 'todo',
            'created_by' => $creator->id,
        ], $attributes));
    }

    private function tasksUrl(): string
    {
        return $this->tenantApi($this->tenant, "workspace/{$this->group->id}/tasks");
    }
}
