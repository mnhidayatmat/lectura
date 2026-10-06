<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Assignment;
use App\Models\AssignmentGroup;
use App\Models\AssignmentGroupMember;
use App\Models\Feedback;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\User;
use App\Notifications\SubmissionReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentAssignmentApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function submissionFor(Assignment $assignment, User $student, array $attributes = []): Submission
    {
        return Submission::create(array_merge([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'submitted_at' => now()->subDay(),
            'status' => 'submitted',
        ], $attributes));
    }

    public function test_lists_published_top_level_assignments_of_enrolled_courses(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $this->createAssignment($course, $lecturer, ['title' => 'Problem Set 1']);
        $this->createAssignment($course, $lecturer, ['title' => 'Portfolio']);
        $this->createAssignment($course, $lecturer, ['title' => 'Hidden draft', 'status' => 'draft']);

        $otherCourse = $this->createCourse($tenant, $lecturer, ['code' => 'ZZZ9999']);
        $this->createAssignment($otherCourse, $lecturer, ['title' => 'Not my course']);

        $response = $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/assignments'))->assertOk();

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertEqualsCanonicalizing(['Problem Set 1', 'Portfolio'], $titles->all());
        $response->assertJsonPath('data.0.status', 'not_submitted')
            ->assertJsonPath('data.0.course.code', 'SKMM1203')
            ->assertJsonPath('data.0.submission_type', 'file');
    }

    public function test_list_reports_submitted_and_graded_state(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $submitted = $this->createAssignment($course, $lecturer, ['title' => 'Lab 1']);
        $this->submissionFor($submitted, $student, ['is_late' => true]);

        $graded = $this->createAssignment($course, $lecturer, ['title' => 'Lab 2']);
        $gradedSubmission = $this->submissionFor($graded, $student);
        StudentMark::create([
            'tenant_id' => $tenant->id,
            'assignment_id' => $graded->id,
            'submission_id' => $gradedSubmission->id,
            'user_id' => $student->id,
            'total_marks' => 18,
            'max_marks' => 20,
            'percentage' => 90,
            'is_final' => true,
            'finalized_at' => now(),
        ]);

        $overdue = $this->createAssignment($course, $lecturer, ['title' => 'Lab 0', 'deadline' => now()->subDay()]);

        $items = collect($this->actingAsApi($student)
            ->getJson($this->tenantApi($tenant, 'student/assignments'))
            ->assertOk()
            ->json('data'))->keyBy('title');

        $this->assertSame('submitted', $items['Lab 1']['status']);
        $this->assertTrue($items['Lab 1']['is_late']);
        $this->assertSame('graded', $items['Lab 2']['status']);
        $this->assertSame(90, $items['Lab 2']['mark']['percentage']);
        $this->assertSame('overdue', $items['Lab 0']['status']);
        $this->assertSame($overdue->id, $items['Lab 0']['id']);
    }

    public function test_shows_detail_with_rules_instruction_and_submit_gate(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $assignment = $this->createAssignment($course, $lecturer, [
            'description' => 'Solve every question.',
            'submission_type' => 'both',
            'instruction_file_path' => 'assignment-instructions/brief.pdf',
            'instruction_filename' => 'brief.pdf',
        ]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/assignments/'.$assignment->id))
            ->assertOk()
            ->assertJsonPath('data.assignment.description', 'Solve every question.')
            ->assertJsonPath('data.assignment.instruction.filename', 'brief.pdf')
            ->assertJsonPath('data.rules.allows_text', true)
            ->assertJsonPath('data.rules.requires_files', false)
            ->assertJsonPath('data.rules.attempts_used', 0)
            ->assertJsonPath('data.rules.attempts_remaining', 0)
            ->assertJsonPath('data.rules.max_file_size_bytes', 26214400)
            ->assertJsonPath('data.status', 'not_submitted')
            ->assertJsonPath('data.submission', null)
            ->assertJsonPath('data.group', null)
            ->assertJsonPath('data.can_submit', true)
            ->assertJsonPath('data.blocked_reason', null);
    }

    public function test_detail_shows_my_submission_released_mark_and_feedback(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $assignment = $this->createAssignment($course, $lecturer);
        $submission = $this->submissionFor($assignment, $student, ['text_content' => 'My answer', 'status' => 'graded']);
        SubmissionFile::create([
            'submission_id' => $submission->id,
            'file_name' => 'report.pdf',
            'file_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'storage_path' => 'submissions/'.$assignment->id.'/report.pdf',
        ]);
        StudentMark::create([
            'tenant_id' => $tenant->id,
            'assignment_id' => $assignment->id,
            'submission_id' => $submission->id,
            'user_id' => $student->id,
            'total_marks' => 15,
            'max_marks' => 20,
            'percentage' => 75,
            'grade' => 'B',
            'is_final' => true,
            'finalized_at' => now(),
        ]);
        Feedback::create([
            'submission_id' => $submission->id,
            'user_id' => $student->id,
            'strengths' => 'Clear working',
            'is_released' => true,
            'released_at' => now(),
        ]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/assignments/'.$assignment->id))
            ->assertOk()
            ->assertJsonPath('data.status', 'graded')
            ->assertJsonPath('data.submission.text_content', 'My answer')
            ->assertJsonPath('data.submission.is_mine', true)
            ->assertJsonPath('data.submission.files.0.file_name', 'report.pdf')
            ->assertJsonPath('data.submission.files.0.download_url', route('api.v1.tenant.student.assignments.files.download', [
                'tenant' => $tenant->slug,
                'assignment' => $assignment->id,
                'file' => SubmissionFile::first()->id,
            ]))
            ->assertJsonPath('data.mark.grade', 'B')
            ->assertJsonPath('data.feedback.strengths', 'Clear working')
            ->assertJsonPath('data.can_submit', false)
            ->assertJsonPath('data.blocked_reason', 'You have already submitted this assignment.');
    }

    public function test_students_of_other_courses_and_other_tenants_cannot_read_assignments(): void
    {
        [$tenant, $lecturer, , $course] = $this->enrolledStudent();
        $assignment = $this->createAssignment($course, $lecturer);
        $stranger = $this->createMember($tenant, 'student');

        $this->actingAsApi($stranger)->getJson($this->tenantApi($tenant, 'student/assignments/'.$assignment->id))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not enrolled in this course.');

        $otherTenant = $this->createTenant();
        $otherLecturer = $this->createMember($otherTenant, 'lecturer');
        $foreign = $this->createAssignment($this->createCourse($otherTenant, $otherLecturer), $otherLecturer);

        $this->getJson($this->tenantApi($tenant, 'student/assignments/'.$foreign->id))->assertNotFound();
    }

    public function test_draft_assignments_are_not_visible_to_students(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $draft = $this->createAssignment($course, $lecturer, ['status' => 'draft']);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/assignments/'.$draft->id))
            ->assertNotFound();
    }

    public function test_closed_assignments_stay_viewable_but_refuse_submissions(): void
    {
        Storage::fake('uploads');
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $closed = $this->createAssignment($course, $lecturer, ['status' => 'marking']);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/assignments/'.$closed->id))
            ->assertOk()
            ->assertJsonPath('data.can_submit', false)
            ->assertJsonPath('data.blocked_reason', 'This assignment is no longer accepting submissions.');

        $this->post(
            $this->tenantApi($tenant, "student/assignments/{$closed->id}/submit"),
            ['files' => [UploadedFile::fake()->create('report.pdf', 120, 'application/pdf')]],
        )->assertStatus(422)
            ->assertJsonPath('message', 'This assignment is no longer accepting submissions.');
    }

    public function test_submits_files_and_notifies_the_lecturer(): void
    {
        Storage::fake('uploads');
        Notification::fake();
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $assignment = $this->createAssignment($course, $lecturer, ['deadline' => now()->addDay()]);

        $this->actingAsApi($student)->post(
            $this->tenantApi($tenant, "student/assignments/{$assignment->id}/submit"),
            [
                'files' => [UploadedFile::fake()->create('report.pdf', 120, 'application/pdf')],
                'notes' => 'Sorry for the formatting.',
            ],
            ['Accept' => 'application/json'],
        )
            ->assertCreated()
            ->assertJsonPath('message', 'Submission uploaded successfully.')
            ->assertJsonPath('data.submission.is_late', false)
            ->assertJsonPath('data.submission.submission_number', 1)
            ->assertJsonPath('data.submission.files.0.file_name', 'report.pdf');

        $this->assertDatabaseHas('submissions', [
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'status' => 'submitted',
            'is_late' => false,
        ]);
        $this->assertDatabaseCount('submission_files', 1);
        Notification::assertSentTo($lecturer, SubmissionReceived::class);
    }

    public function test_submission_after_the_deadline_is_flagged_late(): void
    {
        Storage::fake('uploads');
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $assignment = $this->createAssignment($course, $lecturer, ['deadline' => now()->subDay()]);

        $this->actingAsApi($student)->post(
            $this->tenantApi($tenant, "student/assignments/{$assignment->id}/submit"),
            ['files' => [UploadedFile::fake()->create('late.pdf', 10, 'application/pdf')]],
            ['Accept' => 'application/json'],
        )
            ->assertCreated()
            ->assertJsonPath('message', 'Submission uploaded successfully. (Late submission)')
            ->assertJsonPath('data.submission.is_late', true);
    }

    public function test_submission_validation_follows_the_submission_type(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $fileOnly = $this->createAssignment($course, $lecturer);
        $textOnly = $this->createAssignment($course, $lecturer, ['submission_type' => 'text']);
        $both = $this->createAssignment($course, $lecturer, ['submission_type' => 'both']);

        $this->actingAsApi($student)
            ->postJson($this->tenantApi($tenant, "student/assignments/{$fileOnly->id}/submit"), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files');

        $this->postJson($this->tenantApi($tenant, "student/assignments/{$textOnly->id}/submit"), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('text_content');

        $this->postJson($this->tenantApi($tenant, "student/assignments/{$both->id}/submit"), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files' => 'Please provide either files or text content.']);

        $this->postJson($this->tenantApi($tenant, "student/assignments/{$textOnly->id}/submit"), ['text_content' => 'Done'])
            ->assertCreated();
    }

    public function test_second_submission_is_rejected_unless_resubmission_is_allowed(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $once = $this->createAssignment($course, $lecturer, ['submission_type' => 'text']);
        $this->submissionFor($once, $student);

        $this->actingAsApi($student)
            ->postJson($this->tenantApi($tenant, "student/assignments/{$once->id}/submit"), ['text_content' => 'Again'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already submitted this assignment.');

        $retry = $this->createAssignment($course, $lecturer, [
            'submission_type' => 'text',
            'allow_resubmission' => true,
            'max_resubmissions' => 1,
        ]);
        $this->submissionFor($retry, $student);

        $this->postJson($this->tenantApi($tenant, "student/assignments/{$retry->id}/submit"), ['text_content' => 'Take two'])
            ->assertCreated()
            ->assertJsonPath('data.submission.submission_number', 2);

        $this->postJson($this->tenantApi($tenant, "student/assignments/{$retry->id}/submit"), ['text_content' => 'Take three'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already submitted this assignment.');
    }

    public function test_only_the_group_leader_can_submit_a_group_assignment(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $assignment = $this->createAssignment($course, $lecturer, ['type' => 'group', 'submission_type' => 'text']);
        $leader = $this->createMember($tenant, 'student', ['name' => 'Lead Student']);
        $group = AssignmentGroup::create(['assignment_id' => $assignment->id, 'name' => 'Group A']);
        AssignmentGroupMember::create(['assignment_group_id' => $group->id, 'user_id' => $leader->id, 'is_leader' => true]);
        AssignmentGroupMember::create(['assignment_group_id' => $group->id, 'user_id' => $student->id, 'is_leader' => false]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/assignments/'.$assignment->id))
            ->assertOk()
            ->assertJsonPath('data.group.name', 'Group A')
            ->assertJsonPath('data.group.is_leader', false)
            ->assertJsonPath('data.group.leader_name', 'Lead Student')
            ->assertJsonPath('data.can_submit', false)
            ->assertJsonPath('data.blocked_reason', 'Only the group leader can submit for the group.');

        $this->postJson($this->tenantApi($tenant, "student/assignments/{$assignment->id}/submit"), ['text_content' => 'Ours'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only the group leader can submit for the group.');

        $this->enroll($course->sections()->first(), $leader);
        $this->actingAsApi($leader)
            ->postJson($this->tenantApi($tenant, "student/assignments/{$assignment->id}/submit"), ['text_content' => 'Ours'])
            ->assertCreated()
            ->assertJsonPath('message', 'Group submission uploaded successfully.');

        // The leader's submission is mirrored onto every member, as on the web.
        $this->assertDatabaseHas('submissions', ['assignment_id' => $assignment->id, 'user_id' => $student->id]);
        $this->assertSame(2, Submission::where('assignment_id', $assignment->id)->count());
    }

    public function test_downloads_the_instruction_file_and_own_submission_files(): void
    {
        Storage::fake('uploads');
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        Storage::disk('uploads')->put('assignment-instructions/brief.pdf', '%PDF-1.4');
        $assignment = $this->createAssignment($course, $lecturer, [
            'instruction_file_path' => 'assignment-instructions/brief.pdf',
            'instruction_filename' => 'brief.pdf',
        ]);

        $submission = $this->submissionFor($assignment, $student);
        Storage::disk('uploads')->put('submissions/'.$assignment->id.'/report.pdf', '%PDF-1.4');
        $file = SubmissionFile::create([
            'submission_id' => $submission->id,
            'file_name' => 'report.pdf',
            'file_type' => 'application/pdf',
            'file_size_bytes' => 8,
            'storage_path' => 'submissions/'.$assignment->id.'/report.pdf',
        ]);

        $this->actingAsApi($student)
            ->get($this->tenantApi($tenant, "student/assignments/{$assignment->id}/instruction"))
            ->assertOk()
            ->assertDownload('brief.pdf');

        $this->get($this->tenantApi($tenant, "student/assignments/{$assignment->id}/files/{$file->id}/download"))
            ->assertOk()
            ->assertDownload('report.pdf');

        $classmate = $this->createMember($tenant, 'student');
        $this->enroll($course->sections()->first(), $classmate);
        $this->actingAsApi($classmate)
            ->getJson($this->tenantApi($tenant, "student/assignments/{$assignment->id}/files/{$file->id}/download"))
            ->assertForbidden();
    }
}
