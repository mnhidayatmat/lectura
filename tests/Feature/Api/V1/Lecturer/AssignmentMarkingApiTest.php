<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Feedback;
use App\Models\Rubric;
use App\Models\RubricCriteria;
use App\Models\StudentGroup;
use App\Models\StudentGroupSet;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\FeedbackReleased;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class AssignmentMarkingApiTest extends LecturerApiTestCase
{
    private function assignment(Tenant $tenant, Course $course, User $lecturer, array $attributes = []): Assignment
    {
        return Assignment::create(array_merge([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'created_by' => $lecturer->id,
            'title' => 'Lab Report 1',
            'type' => 'individual',
            'total_marks' => 20,
            'deadline' => now()->addDay(),
            'marking_mode' => 'manual',
            'submission_type' => 'both',
            'status' => 'published',
        ], $attributes));
    }

    private function submit(Assignment $assignment, User $student, array $attributes = []): Submission
    {
        return Submission::create(array_merge([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'submission_number' => 1,
            'text_content' => 'My answer',
            'is_late' => false,
            'submitted_at' => now(),
            'status' => 'submitted',
        ], $attributes));
    }

    /**
     * @return array{0: Tenant, 1: User, 2: User, 3: Assignment, 4: Submission}
     */
    private function individualSetup(): array
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $student = $this->createMember($tenant, 'student');
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'SKM1001']);
        $assignment = $this->assignment($tenant, $course, $lecturer);

        return [$tenant, $lecturer, $student, $assignment, $this->submit($assignment, $student)];
    }

    public function test_lists_assignments_with_marking_progress(): void
    {
        [$tenant, $lecturer, , $assignment] = $this->individualSetup();

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/assignments'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.course.code', 'SKM1001')
            ->assertJsonPath('data.0.total_marks', 20)
            ->assertJsonPath('data.0.counts', ['submissions' => 1, 'graded' => 0]);

        $stranger = $this->createMember($tenant, 'lecturer');
        $this->actingAsApi($stranger)->getJson($this->tenantApi($tenant, 'lecturer/assignments'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->actingAsApi($stranger)->getJson($this->tenantApi($tenant, "lecturer/assignments/{$assignment->id}"))
            ->assertForbidden();
    }

    public function test_marking_without_a_rubric_releases_the_total_and_feedback(): void
    {
        Notification::fake();
        [$tenant, $lecturer, $student, $assignment, $submission] = $this->individualSetup();
        $path = $this->tenantApi($tenant, "lecturer/assignments/{$assignment->id}/submissions/{$submission->id}");

        $this->actingAsApi($lecturer)->getJson($path)
            ->assertOk()
            ->assertJsonPath('data.text_content', 'My answer')
            ->assertJsonPath('data.mark', null)
            ->assertJsonPath('data.assignment.rubric', null);

        $this->actingAsApi($lecturer)->postJson("$path/mark", ['total' => 25])
            ->assertStatus(422)
            ->assertJsonValidationErrors('total');

        $this->actingAsApi($lecturer)->postJson("$path/mark", [
            'total' => 15,
            'feedback_strengths' => 'Clear method',
            'feedback_improvements' => 'Label your axes',
        ])
            ->assertOk()
            ->assertJsonPath('data.percentage', 75);

        $mark = StudentMark::where('user_id', $student->id)->sole();
        $this->assertTrue($mark->is_final);
        $this->assertEquals(15, $mark->total_marks);
        $this->assertSame('graded', $submission->fresh()->status);
        $this->assertTrue(Feedback::where('submission_id', $submission->id)->sole()->is_released);
        Notification::assertSentTo($student, FeedbackReleased::class);
    }

    public function test_rubric_marks_are_checked_per_criterion_and_summed(): void
    {
        Notification::fake();
        [$tenant, $lecturer, $student, $assignment, $submission] = $this->individualSetup();
        $rubric = Rubric::create(['assignment_id' => $assignment->id, 'type' => 'matrix']);
        $method = RubricCriteria::create(['rubric_id' => $rubric->id, 'title' => 'Method', 'max_marks' => 10, 'sort_order' => 1]);
        $result = RubricCriteria::create(['rubric_id' => $rubric->id, 'title' => 'Results', 'max_marks' => 10, 'sort_order' => 2]);
        $path = $this->tenantApi($tenant, "lecturer/assignments/{$assignment->id}/submissions/{$submission->id}/mark");

        $this->actingAsApi($lecturer)->postJson($path, ['criteria' => [$method->id => 11, $result->id => 5]])
            ->assertStatus(422)
            ->assertJsonValidationErrors("criteria.{$method->id}");

        $this->actingAsApi($lecturer)->postJson($path, ['criteria' => [$method->id => 8]])
            ->assertStatus(422)
            ->assertJsonValidationErrors("criteria.{$result->id}");

        $this->actingAsApi($lecturer)->postJson($path, ['criteria' => [$method->id => 8, $result->id => 6.5]])
            ->assertOk()
            ->assertJsonPath('data.total_marks', 14.5);
    }

    public function test_a_group_submission_marks_every_member(): void
    {
        Notification::fake();
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $leader = $this->createMember($tenant, 'student');
        $member = $this->createMember($tenant, 'student');
        $course = $this->createCourse($tenant, $lecturer);
        $set = StudentGroupSet::create(['tenant_id' => $tenant->id, 'course_id' => $course->id, 'type' => 'lab', 'name' => 'Labs', 'created_by' => $lecturer->id]);
        $group = StudentGroup::create(['student_group_set_id' => $set->id, 'name' => 'Group A']);
        $assignment = $this->assignment($tenant, $course, $lecturer, ['type' => 'group', 'student_group_set_id' => $set->id]);

        Storage::fake('local');
        Storage::disk('local')->put('submissions/report.pdf', 'pdf');
        $leaderCopy = $this->submit($assignment, $leader, ['student_group_id' => $group->id]);
        $leaderCopy->files()->create(['file_name' => 'report.pdf', 'file_type' => 'application/pdf', 'storage_path' => 'submissions/report.pdf', 'file_size_bytes' => 3]);
        $this->submit($assignment, $member, ['student_group_id' => $group->id]);

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, "lecturer/assignments/{$assignment->id}"))
            ->assertOk()
            ->assertJsonCount(1, 'data.submissions')
            ->assertJsonPath('data.submissions.0.id', $leaderCopy->id)
            ->assertJsonPath('data.submissions.0.group.name', 'Group A');

        $base = $this->tenantApi($tenant, "lecturer/assignments/{$assignment->id}/submissions/{$leaderCopy->id}");
        $fileId = $leaderCopy->files()->value('id');
        $this->actingAsApi($lecturer)->get("$base/files/$fileId")->assertOk();

        $this->actingAsApi($lecturer)->postJson("$base/mark", ['total' => 18])
            ->assertOk()
            ->assertJsonPath('data.members_marked', 2);

        $this->assertSame(2, StudentMark::where('assignment_id', $assignment->id)->where('total_marks', 18)->count());
    }

    public function test_a_submission_from_another_assignment_is_not_found(): void
    {
        [$tenant, $lecturer, $student, $assignment] = $this->individualSetup();
        $other = $this->assignment($tenant, $assignment->course, $lecturer, ['title' => 'Other']);
        $foreign = $this->submit($other, $student);

        $this->actingAsApi($lecturer)
            ->postJson($this->tenantApi($tenant, "lecturer/assignments/{$assignment->id}/submissions/{$foreign->id}/mark"), ['total' => 5])
            ->assertNotFound();
    }
}
