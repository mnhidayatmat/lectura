<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\AssessmentScore;
use App\Models\Assignment;
use App\Models\Feedback;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentMarkApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function finalMark(Tenant $tenant, Assignment $assignment, User $student, array $attributes = []): StudentMark
    {
        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'submitted_at' => now()->subDay(),
        ]);

        Feedback::create([
            'submission_id' => $submission->id,
            'user_id' => $student->id,
            'strengths' => 'Clear method',
            'performance_level' => 'advanced',
            'is_released' => true,
            'released_at' => now(),
        ]);

        return StudentMark::create(array_merge([
            'tenant_id' => $tenant->id,
            'assignment_id' => $assignment->id,
            'submission_id' => $submission->id,
            'user_id' => $student->id,
            'total_marks' => 18,
            'max_marks' => 20,
            'percentage' => 90,
            'grade' => 'A',
            'is_final' => true,
            'finalized_at' => now(),
        ], $attributes));
    }

    public function test_lists_marks_with_feedback_and_released_assessment_scores(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $graded = $this->createAssignment($course, $lecturer, ['title' => 'Lab Report', 'status' => 'closed']);
        $this->finalMark($tenant, $graded, $student);
        $this->createAssignment($course, $lecturer, ['title' => 'Problem Set 2']);

        $assessment = $this->createAssessment($course);
        AssessmentScore::create([
            'tenant_id' => $tenant->id,
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
            'raw_marks' => 40,
            'max_marks' => 50,
            'weighted_marks' => 16,
            'percentage' => 80,
            'is_released' => true,
            'released_at' => now(),
            'feedback' => 'Good work',
        ]);
        $unreleased = $this->createAssessment($course, ['title' => 'Final Exam']);
        AssessmentScore::create([
            'tenant_id' => $tenant->id,
            'assessment_id' => $unreleased->id,
            'user_id' => $student->id,
            'raw_marks' => 10,
            'max_marks' => 50,
            'weighted_marks' => 4,
            'percentage' => 20,
            'is_released' => false,
        ]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/marks?filter=graded'))
            ->assertOk()
            ->assertJsonPath('data.filter', 'graded')
            ->assertJsonPath('data.stats.total_assignments', 2)
            ->assertJsonPath('data.stats.graded_count', 1)
            ->assertJsonPath('data.stats.pending_count', 1)
            ->assertJsonPath('data.stats.average_percentage', 90)
            ->assertJsonCount(1, 'data.assignments')
            ->assertJsonPath('data.assignments.0.status', 'graded')
            ->assertJsonPath('data.assignments.0.mark.grade', 'A')
            ->assertJsonPath('data.assignments.0.feedback.strengths', 'Clear method')
            ->assertJsonCount(1, 'data.assessment_scores')
            ->assertJsonPath('data.assessment_scores.0.percentage', 80)
            ->assertJsonPath('data.assessment_scores.0.course.code', 'SKMM1203')
            ->assertJsonPath('data.assessment_scores.0.answer_script', null);

        $this->getJson($this->tenantApi($tenant, 'student/marks?filter=pending'))
            ->assertOk()
            ->assertJsonCount(1, 'data.assignments')
            ->assertJsonPath('data.assignments.0.status', 'not_submitted')
            ->assertJsonPath('data.assignments.0.mark', null)
            ->assertJsonPath('data.assessment_scores', []);

        $this->getJson($this->tenantApi($tenant, 'student/marks?filter=everything'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('filter');
    }

    public function test_shows_a_released_mark_with_feedback(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $mark = $this->finalMark($tenant, $this->createAssignment($course, $lecturer, ['title' => 'Lab Report']), $student);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/marks/'.$mark->id))
            ->assertOk()
            ->assertJsonPath('data.mark.percentage', 90)
            ->assertJsonPath('data.assignment.title', 'Lab Report')
            ->assertJsonPath('data.course.code', 'SKMM1203')
            ->assertJsonPath('data.submission.is_late', false)
            ->assertJsonPath('data.feedback.performance_level', 'advanced');
    }

    public function test_cannot_view_another_students_or_unreleased_marks(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $classmate = $this->createMember($tenant, 'student');
        $theirs = $this->finalMark($tenant, $this->createAssignment($course, $lecturer), $classmate);
        $draft = $this->finalMark($tenant, $this->createAssignment($course, $lecturer), $student, ['is_final' => false]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/marks/'.$theirs->id))
            ->assertForbidden();

        $this->getJson($this->tenantApi($tenant, 'student/marks/'.$draft->id))
            ->assertForbidden()
            ->assertJsonPath('message', 'These marks have not been released yet.');
    }

    public function test_mark_from_another_institution_is_not_found(): void
    {
        [$tenant, , $student] = $this->enrolledStudent();
        $otherTenant = $this->createTenant();
        $otherLecturer = $this->createMember($otherTenant, 'lecturer');
        $foreignCourse = $this->createCourse($otherTenant, $otherLecturer);
        $foreignMark = $this->finalMark($otherTenant, $this->createAssignment($foreignCourse, $otherLecturer), $student);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/marks/'.$foreignMark->id))
            ->assertNotFound();
    }

    public function test_downloads_a_released_answer_script(): void
    {
        Storage::fake('uploads');
        [$tenant, , $student, $course] = $this->enrolledStudent();
        Storage::disk('uploads')->put('answer-scripts/script.pdf', '%PDF-1.4');

        $score = AssessmentScore::create([
            'tenant_id' => $tenant->id,
            'assessment_id' => $this->createAssessment($course)->id,
            'user_id' => $student->id,
            'raw_marks' => 40,
            'max_marks' => 50,
            'weighted_marks' => 16,
            'percentage' => 80,
            'is_released' => true,
            'released_at' => now(),
            'answer_script_path' => 'answer-scripts/script.pdf',
            'answer_script_filename' => 'Mid-term script.pdf',
        ]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/marks'))
            ->assertJsonPath('data.assessment_scores.0.answer_script.download_url', route('api.v1.tenant.student.marks.answer-script', [
                'tenant' => $tenant->slug,
                'score' => $score->id,
            ]));

        $this->get($this->tenantApi($tenant, "student/marks/assessment-scores/{$score->id}/answer-script"))
            ->assertOk()
            ->assertDownload('Mid-term script.pdf');

        $score->update(['is_released' => false]);

        $this->getJson($this->tenantApi($tenant, "student/marks/assessment-scores/{$score->id}/answer-script"))
            ->assertNotFound();
    }
}
