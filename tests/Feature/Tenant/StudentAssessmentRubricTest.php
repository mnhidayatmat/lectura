<?php

namespace Tests\Feature\Tenant;

use App\Models\AssessmentScore;
use App\Models\Rubric;
use App\Models\RubricCriteria;
use App\Models\RubricLevel;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Student\BuildsStudentFixtures;

/**
 * Once marks are released, the student's assessment page shows the rubric
 * as the lecturer filled it in: each criterion's mark and the level it reached.
 */
class StudentAssessmentRubricTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    public function test_released_marks_show_the_filled_rubric(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $assessment = $this->createAssessment($course, ['title' => 'Lab 1', 'requires_submission' => true, 'total_marks' => 100]);
        $rubric = Rubric::create(['assessment_id' => $assessment->id, 'type' => 'matrix']);
        $tools = RubricCriteria::create(['rubric_id' => $rubric->id, 'title' => 'Understanding of tools', 'max_marks' => 10, 'sort_order' => 0]);
        $time = RubricCriteria::create(['rubric_id' => $rubric->id, 'title' => 'Time Management', 'max_marks' => 10, 'sort_order' => 1]);
        foreach ([$tools, $time] as $criterion) {
            foreach ([['Level 0 (0–1)', 1], ['Level 1 (1–3)', 3], ['Level 2 (3–5)', 5]] as $i => [$label, $marks]) {
                RubricLevel::create(['rubric_criteria_id' => $criterion->id, 'label' => $label, 'description' => "{$criterion->title} descriptor {$i}", 'marks' => $marks, 'sort_order' => $i]);
            }
        }

        $score = AssessmentScore::create([
            'tenant_id' => $tenant->id,
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
            'raw_marks' => 6,
            'max_marks' => 100,
            'weighted_marks' => 6,
            'percentage' => 6,
            'is_released' => false,
            'criteria_marks' => [(string) $tools->id => 2.5, (string) $time->id => 3.5],
        ]);

        $url = "/{$tenant->slug}/courses/{$course->id}/assessments/{$assessment->id}/view";

        $this->actingAs($student)->get($url)->assertOk()->assertDontSee('Understanding of tools descriptor');

        $score->update(['is_released' => true, 'released_at' => now()]);

        $this->actingAs($student)->get($url)->assertOk()
            ->assertSeeInOrder(['Rubric', 'Understanding of tools', '2.5', 'Level 1 (1–3)', 'Time Management', '3.5', 'Level 2 (3–5)']);

        $this->assertSame('Level 1 (1–3)', $tools->load('levels')->levelFor(2.5)?->label);
        $this->assertSame('Level 1 (1–3)', $tools->levelFor(3.0)?->label);
        $this->assertSame('Level 0 (0–1)', $tools->levelFor(0.0)?->label);
        $this->assertSame('Level 2 (3–5)', $tools->levelFor(9.0)?->label);
        $this->assertNull($tools->levelFor(null));
    }
}
