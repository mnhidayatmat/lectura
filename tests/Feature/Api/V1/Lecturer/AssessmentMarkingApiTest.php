<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentSubmission;
use App\Models\Course;
use App\Models\Rubric;
use App\Models\RubricCriteria;
use App\Models\Section;
use App\Models\StudentGroup;
use App\Models\StudentGroupSet;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AssessmentMarksReleased;
use Illuminate\Support\Facades\Notification;

class AssessmentMarkingApiTest extends LecturerApiTestCase
{
    private function assessment(Course $course, array $attributes = []): Assessment
    {
        return Assessment::create(array_merge([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'title' => 'Test 1',
            'type' => 'test',
            'weightage' => 20,
            'total_marks' => 50,
            'status' => 'active',
            'requires_submission' => false,
        ], $attributes));
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Course, 3: Section, 4: User, 5: Assessment}
     */
    private function setUpCourse(): array
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $section = $this->createSection($course);
        $student = $this->createMember($tenant, 'student', ['name' => 'Aina Sofea']);
        $this->enroll($section, $student);

        return [$tenant, $lecturer, $course, $section, $student, $this->assessment($course)];
    }

    public function test_index_nests_parts_under_their_parent(): void
    {
        [$tenant, $lecturer, $course, , , $test] = $this->setUpCourse();
        $project = $this->assessment($course, ['title' => 'Project', 'sort_order' => 2]);
        $this->assessment($course, ['title' => 'Proposal', 'parent_id' => $project->id]);

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/assessments"))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $test->id)
            ->assertJsonPath('data.1.children.0.title', 'Proposal')
            ->assertJsonPath('meta.students', 1);
    }

    public function test_a_mark_is_saved_unreleased_then_released_and_retracted(): void
    {
        Notification::fake();
        [$tenant, $lecturer, , , $student, $assessment] = $this->setUpCourse();
        $base = $this->tenantApi($tenant, "lecturer/assessments/{$assessment->id}");

        $this->actingAsApi($lecturer)->putJson("$base/scores/{$student->id}", ['raw_marks' => 60])
            ->assertStatus(422)
            ->assertJsonValidationErrors('raw_marks');

        $this->actingAsApi($lecturer)->putJson("$base/scores/{$student->id}", ['raw_marks' => 40, 'feedback' => 'Good'])
            ->assertOk()
            ->assertJsonPath('data.percentage', 80)
            ->assertJsonPath('data.weighted_marks', 16)
            ->assertJsonPath('data.is_released', false);

        $this->actingAsApi($lecturer)->getJson($base)
            ->assertOk()
            ->assertJsonPath('data.students.0.student.name', 'Aina Sofea')
            ->assertJsonPath('data.stats.graded', 1)
            ->assertJsonPath('data.stats.released', 0);

        $this->actingAsApi($lecturer)->postJson("$base/release")
            ->assertOk()
            ->assertJsonPath('data.released', 1);
        Notification::assertSentTo($student, AssessmentMarksReleased::class);

        $score = AssessmentScore::where('user_id', $student->id)->sole();
        $this->assertTrue($score->is_released);

        $this->actingAsApi($lecturer)->postJson("$base/scores/{$score->id}/unrelease")
            ->assertOk()
            ->assertJsonPath('data.score.is_released', false);

        // Changing a released mark takes it back to unreleased, as on the web.
        $this->actingAsApi($lecturer)->postJson("$base/release")->assertOk();
        $this->actingAsApi($lecturer)->putJson("$base/scores/{$student->id}", ['raw_marks' => 45])
            ->assertOk()
            ->assertJsonPath('data.is_released', false);
    }

    public function test_weighted_rubric_uses_the_web_formula(): void
    {
        [$tenant, $lecturer, , , $student, $assessment] = $this->setUpCourse();
        $rubric = Rubric::create(['assessment_id' => $assessment->id, 'type' => 'matrix']);
        $a = RubricCriteria::create(['rubric_id' => $rubric->id, 'title' => 'A', 'max_marks' => 10, 'weightage' => 60, 'sort_order' => 1]);
        $b = RubricCriteria::create(['rubric_id' => $rubric->id, 'title' => 'B', 'max_marks' => 5, 'weightage' => 40, 'sort_order' => 2]);

        // (5/10)·0.6·50 + (5/5)·0.4·50 = 15 + 20 = 35
        $this->actingAsApi($lecturer)
            ->putJson($this->tenantApi($tenant, "lecturer/assessments/{$assessment->id}/scores/{$student->id}"), [
                'criteria_marks' => [$a->id => 5, $b->id => 5],
            ])
            ->assertOk()
            ->assertJsonPath('data.raw_marks', 35)
            ->assertJsonPath("data.criteria_marks.{$b->id}", 5);
    }

    public function test_group_marks_and_retraction_cover_the_whole_group(): void
    {
        Notification::fake();
        [$tenant, $lecturer, $course, $section, $leader] = $this->setUpCourse();
        $member = $this->createMember($tenant, 'student');
        $this->enroll($section, $member);
        $set = StudentGroupSet::create(['tenant_id' => $tenant->id, 'course_id' => $course->id, 'type' => 'project', 'name' => 'Projects', 'created_by' => $lecturer->id]);
        $group = StudentGroup::create(['student_group_set_id' => $set->id, 'name' => 'Team 1']);
        $assessment = $this->assessment($course, ['title' => 'Report', 'requires_submission' => true, 'student_group_set_id' => $set->id]);
        foreach ([$leader, $member] as $student) {
            AssessmentSubmission::create([
                'tenant_id' => $tenant->id, 'assessment_id' => $assessment->id, 'user_id' => $student->id,
                'student_group_id' => $group->id, 'submitted_at' => now(), 'status' => 'submitted',
            ]);
        }
        $base = $this->tenantApi($tenant, "lecturer/assessments/{$assessment->id}");

        $this->actingAsApi($lecturer)->putJson("$base/scores/{$leader->id}", ['raw_marks' => 30])->assertOk();
        $this->assertSame(2, AssessmentScore::where('assessment_id', $assessment->id)->where('raw_marks', 30)->count());

        $leaderScore = AssessmentScore::where('user_id', $leader->id)->sole();
        $this->actingAsApi($lecturer)->postJson("$base/release", ['score_ids' => [$leaderScore->id]])
            ->assertOk()
            ->assertJsonPath('data.released', 2);

        $this->actingAsApi($lecturer)->postJson("$base/scores/{$leaderScore->id}/unrelease")
            ->assertOk()
            ->assertJsonPath('data.retracted', 2);
    }

    public function test_outsiders_parents_and_strangers_are_refused(): void
    {
        [$tenant, $lecturer, $course, , $student, $assessment] = $this->setUpCourse();
        $outsider = $this->createMember($tenant, 'student');
        $colleague = $this->createMember($tenant, 'lecturer');
        $parent = $this->assessment($course, ['title' => 'Project']);
        $this->assessment($course, ['title' => 'Part', 'parent_id' => $parent->id]);

        $this->actingAsApi($lecturer)
            ->putJson($this->tenantApi($tenant, "lecturer/assessments/{$assessment->id}/scores/{$outsider->id}"), ['raw_marks' => 10])
            ->assertNotFound();

        $this->actingAsApi($lecturer)
            ->putJson($this->tenantApi($tenant, "lecturer/assessments/{$parent->id}/scores/{$student->id}"), ['raw_marks' => 10])
            ->assertStatus(422);

        $this->actingAsApi($colleague)->getJson($this->tenantApi($tenant, "lecturer/assessments/{$assessment->id}"))
            ->assertForbidden();

        $otherTenant = $this->createTenant();
        $otherLecturer = $this->createMember($otherTenant, 'lecturer');
        $this->actingAsApi($otherLecturer)->getJson($this->tenantApi($otherTenant, "lecturer/assessments/{$assessment->id}"))
            ->assertNotFound();
    }
}
