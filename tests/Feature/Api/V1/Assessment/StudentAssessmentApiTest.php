<?php

namespace Tests\Feature\Api\V1\Assessment;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionFile;
use App\Models\Course;
use App\Models\Rubric;
use App\Models\RubricCriteria;
use App\Models\Section;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\StudentGroupSet;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AssessmentSubmissionReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentAssessmentApiTest extends ApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private Course $course;

    private Section $section;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
        Notification::fake();

        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'SKM3013', 'title' => 'Fluid Mechanics']);
        $this->section = $this->createSection($this->course, [], [$this->lecturer]);
        $this->student = $this->createMember($this->tenant, 'student');
        $this->enroll($this->section, $this->student);
    }

    public function test_lists_submission_assessments_grouped_by_course(): void
    {
        $open = $this->createAssessment(['title' => 'Lab Report 1']);
        $graded = $this->createAssessment(['title' => 'Assignment 1', 'type' => 'assignment', 'status' => 'completed']);
        $this->createAssessment(['title' => 'Draft', 'status' => 'draft']);
        $this->createAssessment(['title' => 'Final Exam', 'requires_submission' => false]);

        AssessmentScore::create([
            'tenant_id' => $this->tenant->id,
            'assessment_id' => $graded->id,
            'user_id' => $this->student->id,
            'raw_marks' => 40,
            'max_marks' => 50,
            'weighted_marks' => 8,
            'percentage' => 80,
            'is_released' => true,
        ]);

        $this->actingAsApi($this->student)->getJson($this->tenantApi($this->tenant, 'assessments'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course.code', 'SKM3013')
            ->assertJsonCount(2, 'data.0.assessments')
            ->assertJsonFragment(['id' => $open->id, 'submission_state' => 'not_submitted'])
            ->assertJsonFragment(['id' => $graded->id, 'submission_state' => 'released', 'type_label' => 'Assignment']);
    }

    public function test_show_returns_rules_and_flags_before_submitting(): void
    {
        $assessment = $this->createAssessment([
            'instruction_file_path' => 'assessment_instructions/brief.pdf',
            'instruction_file_name' => 'brief.pdf',
        ]);
        Storage::disk('uploads')->put('assessment_instructions/brief.pdf', 'brief');

        $this->actingAsApi($this->student)->getJson($this->showUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.title', 'Lab Report 1')
            ->assertJsonPath('data.total_marks', 50)
            ->assertJsonPath('data.instruction_file.extension', 'pdf')
            ->assertJsonPath('data.submission_rules.accepted_extensions', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])
            ->assertJsonPath('data.submission_rules.max_file_size_kb', 25600)
            ->assertJsonPath('data.submission', null)
            ->assertJsonPath('data.can_submit', true)
            ->assertJsonPath('data.can_resubmit', false);

        $this->get($this->showUrl($assessment).'/instruction')->assertOk()->assertDownload('brief.pdf');
    }

    public function test_student_can_submit_files(): void
    {
        $assessment = $this->createAssessment();

        $response = $this->actingAsApi($this->student)->postJson($this->showUrl($assessment).'/submit', [
            'files' => [
                UploadedFile::fake()->create('report.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->create('photo.jpg', 80, 'image/jpeg'),
            ],
            'notes' => 'Please see page 2.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Submission uploaded successfully.')
            ->assertJsonPath('data.submission.status', 'submitted')
            ->assertJsonPath('data.submission.notes', 'Please see page 2.')
            ->assertJsonCount(2, 'data.submission.files')
            ->assertJsonPath('data.can_submit', false)
            ->assertJsonPath('data.can_resubmit', true);

        $file = AssessmentSubmissionFile::where('file_name', 'report.pdf')->firstOrFail();
        Storage::disk('uploads')->assertExists($file->storage_path);
        Notification::assertSentTo($this->lecturer, AssessmentSubmissionReceived::class);

        $this->get($response->json('data.submission.files.0.download_url'))->assertOk();
    }

    public function test_late_submission_is_flagged(): void
    {
        $assessment = $this->createAssessment(['due_date' => now()->subDay()]);

        $this->actingAsApi($this->student)->postJson($this->showUrl($assessment).'/submit', [
            'files' => [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')],
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Submission uploaded successfully. (Late submission)')
            ->assertJsonPath('data.submission.is_late', true);
    }

    public function test_submit_validates_files(): void
    {
        $assessment = $this->createAssessment();

        $this->actingAsApi($this->student)->postJson($this->showUrl($assessment).'/submit', [
            'files' => [
                UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
                UploadedFile::fake()->create('huge.pdf', 26000, 'application/pdf'),
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files.0', 'files.1']);

        $this->postJson($this->showUrl($assessment).'/submit', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files');

        $this->assertDatabaseCount('assessment_submissions', 0);
    }

    public function test_student_not_enrolled_is_forbidden(): void
    {
        $assessment = $this->createAssessment();
        $outsider = $this->createMember($this->tenant, 'student');

        $this->actingAsApi($outsider)->getJson($this->showUrl($assessment))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not enrolled in this course.');
    }

    public function test_assessment_from_another_institution_is_not_found(): void
    {
        $otherTenant = $this->createTenant();
        $otherCourse = $this->createCourse($otherTenant, $this->createMember($otherTenant, 'lecturer'));
        $foreign = Assessment::create([
            'tenant_id' => $otherTenant->id,
            'course_id' => $otherCourse->id,
            'title' => 'Foreign',
            'type' => 'lab',
            'weightage' => 10,
            'total_marks' => 20,
            'status' => 'active',
            'requires_submission' => true,
        ]);

        $this->actingAsApi($this->student)
            ->getJson($this->tenantApi($this->tenant, "assessments/courses/{$this->course->id}/{$foreign->id}"))
            ->assertNotFound();
    }

    public function test_resubmit_replaces_previous_files(): void
    {
        $assessment = $this->createAssessment();

        $this->actingAsApi($this->student)->postJson($this->showUrl($assessment).'/submit', [
            'files' => [UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf')],
        ])->assertCreated();

        $oldPath = AssessmentSubmissionFile::firstOrFail()->storage_path;

        $this->postJson($this->showUrl($assessment).'/resubmit', [
            'files' => [UploadedFile::fake()->create('v2.pdf', 10, 'application/pdf')],
            'notes' => 'Updated',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Submission updated successfully.')
            ->assertJsonCount(1, 'data.submission.files')
            ->assertJsonPath('data.submission.files.0.name', 'v2.pdf');

        Storage::disk('uploads')->assertMissing($oldPath);
        $this->assertDatabaseCount('assessment_submissions', 1);
    }

    public function test_student_can_delete_an_ungraded_submission_but_not_a_graded_one(): void
    {
        $assessment = $this->createAssessment();

        $this->actingAsApi($this->student)->postJson($this->showUrl($assessment).'/submit', [
            'files' => [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')],
        ])->assertCreated();

        $this->deleteJson($this->showUrl($assessment).'/submission')
            ->assertOk()
            ->assertJsonPath('data.submission', null)
            ->assertJsonPath('data.can_submit', true);

        $this->assertDatabaseCount('assessment_submissions', 0);

        AssessmentSubmission::create([
            'tenant_id' => $this->tenant->id,
            'assessment_id' => $assessment->id,
            'user_id' => $this->student->id,
            'submitted_at' => now(),
            'status' => 'graded',
        ]);

        $this->deleteJson($this->showUrl($assessment).'/submission')
            ->assertStatus(422)
            ->assertJsonValidationErrors('submission');

        $this->postJson($this->showUrl($assessment).'/resubmit', [
            'files' => [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files');
    }

    public function test_group_member_who_is_not_leader_cannot_submit(): void
    {
        $member = $this->student;
        $leader = $this->createMember($this->tenant, 'student', ['name' => 'Hakim Leader']);
        $this->enroll($this->section, $leader);
        $assessment = $this->createGroupAssessment([$leader, $member], $leader);

        $this->actingAsApi($member)->getJson($this->showUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.is_group', true)
            ->assertJsonPath('data.group.is_leader', false)
            ->assertJsonPath('data.group.leader.name', 'Hakim Leader')
            ->assertJsonPath('data.can_submit', false)
            ->assertJsonPath('data.notice', 'Waiting for group leader to submit. Hakim Leader will submit on behalf of your group.');

        $this->postJson($this->showUrl($assessment).'/submit', [
            'files' => [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files');
    }

    public function test_group_leader_submission_is_mirrored_and_visible_to_members(): void
    {
        $member = $this->student;
        $leader = $this->createMember($this->tenant, 'student');
        $this->enroll($this->section, $leader);
        $outsider = $this->createMember($this->tenant, 'student');
        $this->enroll($this->section, $outsider);
        $assessment = $this->createGroupAssessment([$leader, $member], $leader);

        $this->actingAsApi($leader)->postJson($this->showUrl($assessment).'/submit', [
            'files' => [UploadedFile::fake()->create('group-report.pdf', 10, 'application/pdf')],
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Group submission uploaded successfully.');

        $this->assertDatabaseHas('assessment_submissions', ['assessment_id' => $assessment->id, 'user_id' => $member->id]);

        $downloadUrl = $this->actingAsApi($member)->getJson($this->showUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.submission.is_group_submission', true)
            ->assertJsonPath('data.submission.files.0.name', 'group-report.pdf')
            ->assertJsonPath('data.can_delete', false)
            ->json('data.submission.files.0.download_url');

        $this->get($downloadUrl)->assertOk();

        $this->actingAsApi($outsider)->get($downloadUrl)->assertForbidden();
    }

    public function test_released_score_includes_rubric_criteria_marks(): void
    {
        $assessment = $this->createAssessment();
        $rubric = Rubric::create(['assessment_id' => $assessment->id, 'type' => 'matrix']);
        $analysis = RubricCriteria::create(['rubric_id' => $rubric->id, 'title' => 'Analysis', 'max_marks' => 30, 'sort_order' => 1]);
        $writing = RubricCriteria::create(['rubric_id' => $rubric->id, 'title' => 'Writing', 'max_marks' => 20, 'sort_order' => 2]);

        $submission = AssessmentSubmission::create([
            'tenant_id' => $this->tenant->id,
            'assessment_id' => $assessment->id,
            'user_id' => $this->student->id,
            'submitted_at' => now(),
            'status' => 'graded',
        ]);

        AssessmentScore::create([
            'tenant_id' => $this->tenant->id,
            'assessment_id' => $assessment->id,
            'user_id' => $this->student->id,
            'assessment_submission_id' => $submission->id,
            'raw_marks' => 42.5,
            'max_marks' => 50,
            'weighted_marks' => 8.5,
            'percentage' => 85,
            'is_released' => true,
            'released_at' => now(),
            'feedback' => 'Strong analysis.',
            'criteria_marks' => [(string) $analysis->id => 27.5, (string) $writing->id => 15],
        ]);

        $this->actingAsApi($this->student)->getJson($this->showUrl($assessment))
            ->assertOk()
            ->assertJsonPath('data.score.raw_marks', 42.5)
            ->assertJsonPath('data.score.feedback', 'Strong analysis.')
            ->assertJsonPath('data.score.criteria.0.title', 'Analysis')
            ->assertJsonPath('data.score.criteria.0.marks', 27.5)
            ->assertJsonPath('data.score.criteria.1.marks', 15)
            ->assertJsonPath('data.can_resubmit', false)
            ->assertJsonPath('data.notice', null);
    }

    private function createAssessment(array $attributes = []): Assessment
    {
        return Assessment::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'course_id' => $this->course->id,
            'title' => 'Lab Report 1',
            'type' => 'lab',
            'weightage' => 10,
            'total_marks' => 50,
            'status' => 'active',
            'requires_submission' => true,
            'due_date' => now()->addWeek(),
        ], $attributes));
    }

    /**
     * @param  array<int, User>  $members
     */
    private function createGroupAssessment(array $members, User $leader): Assessment
    {
        $set = StudentGroupSet::create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $this->course->id,
            'type' => 'lab',
            'name' => 'Lab Groups',
            'created_by' => $this->lecturer->id,
        ]);

        $group = StudentGroup::create(['student_group_set_id' => $set->id, 'name' => 'Lab Group 1']);

        foreach ($members as $member) {
            StudentGroupMember::create([
                'student_group_id' => $group->id,
                'user_id' => $member->id,
                'role' => $member->is($leader) ? 'leader' : 'member',
                'joined_at' => now(),
            ]);
        }

        return $this->createAssessment(['student_group_set_id' => $set->id, 'title' => 'Group Lab Report']);
    }

    private function showUrl(Assessment $assessment): string
    {
        return $this->tenantApi($this->tenant, "assessments/courses/{$assessment->course_id}/{$assessment->id}");
    }
}
