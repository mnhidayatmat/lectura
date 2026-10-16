<?php

namespace Tests\Feature\Tenant;

use App\Models\AssessmentSubmission;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Student\BuildsStudentFixtures;

/**
 * The student dashboard opens with the assessments still to hand in, each
 * coloured by how close its due date is, and submitted ones in green.
 */
class StudentDashboardDueAssessmentsTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    public function test_due_assessments_are_highlighted_and_graded_by_urgency(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $this->createAssessment($course, ['title' => 'Lab 1 report', 'requires_submission' => true, 'due_date' => now()->addHours(20)]);
        $this->createAssessment($course, ['title' => 'Assignment 1', 'requires_submission' => true, 'due_date' => now()->addDays(4)]);
        $this->createAssessment($course, ['title' => 'Project report', 'requires_submission' => true, 'due_date' => now()->addDays(20)]);
        $done = $this->createAssessment($course, ['title' => 'Quiz upload', 'requires_submission' => true, 'due_date' => now()->addDays(3)]);
        $this->createAssessment($course, ['title' => 'Old essay', 'requires_submission' => true, 'due_date' => now()->subDay()]);
        $this->createAssessment($course, ['title' => 'Final exam', 'due_date' => now()->addDays(2)]);
        AssessmentSubmission::create([
            'tenant_id' => $tenant->id,
            'assessment_id' => $done->id,
            'user_id' => $student->id,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($student)->get("/{$tenant->slug}/dashboard")->assertOk();

        $items = $response->viewData('dueAssessments');
        $this->assertSame(
            ['Lab 1 report' => 'urgent', 'Quiz upload' => 'submitted', 'Assignment 1' => 'soon', 'Project report' => 'later'],
            $items->mapWithKeys(fn ($item) => [$item['assessment']->title => $item['tone']])->all(),
        );
        $response->assertSeeInOrder(['Assessments due', '3 to submit', 'Lab 1 report', 'Due soon', 'Quiz upload', 'Submitted', 'Welcome back'])
            ->assertDontSee('Old essay')
            ->assertDontSee('Final exam');
    }

    public function test_the_block_is_hidden_when_nothing_is_due(): void
    {
        [$tenant, , $student] = $this->enrolledStudent();

        $this->actingAs($student)->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertDontSee('Assessments due');
    }
}
