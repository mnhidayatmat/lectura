<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Performance\PerformanceAggregatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Course $course;

    private Section $section;

    private PerformanceAggregatorService $aggregator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test U', 'slug' => 'test-u', 'timezone' => 'UTC', 'locale' => 'en',
        ]);
        app()->instance('current_tenant', $this->tenant);

        $lecturer = User::factory()->create();

        $this->course = Course::create([
            'tenant_id' => $this->tenant->id,
            'lecturer_id' => $lecturer->id,
            'code' => 'BTG1000',
            'title' => 'Test Course',
            'status' => 'active',
        ]);

        $this->section = Section::create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $this->course->id,
            'name' => 'Section 1',
            'code' => 'S1',
            'is_active' => true,
        ]);

        $this->aggregator = app(PerformanceAggregatorService::class);
    }

    private function enrol(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        SectionStudent::create([
            'section_id' => $this->section->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user;
    }

    private function assessment(string $title, float $total = 20, float $weightage = 20, ?int $parentId = null): Assessment
    {
        return Assessment::create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $this->course->id,
            'parent_id' => $parentId,
            'title' => $title,
            'type' => 'other',
            'total_marks' => $total,
            'weightage' => $weightage,
            'status' => 'active',
        ]);
    }

    private function score(Assessment $a, User $u, float $raw, bool $released = false): AssessmentScore
    {
        return AssessmentScore::create([
            'tenant_id' => $this->tenant->id,
            'assessment_id' => $a->id,
            'user_id' => $u->id,
            'raw_marks' => $raw,
            'max_marks' => $a->total_marks,
            'weighted_marks' => $raw,
            'percentage' => $raw / (float) $a->total_marks * 100,
            'is_released' => $released,
        ]);
    }

    /**
     * The reported bug: a course graded entirely in the Assessments system
     * showed no marks, because aggregation only ever read StudentMark.
     */
    public function test_course_performance_includes_assessment_scores(): void
    {
        $alice = $this->enrol('Alice');
        $bob = $this->enrol('Bob');

        $cs = $this->assessment('Case Study 2');
        $this->score($cs, $alice, 16.0);   // 80%
        $this->score($cs, $bob, 12.0);     // 60%

        $data = $this->aggregator->getCoursePerformance($this->course);

        $this->assertSame(70.0, $data['avg_mark']);
        $this->assertCount(1, $data['assignment_stats']);
        $this->assertSame('Case Study 2', $data['assignment_stats'][0]['title']);
        $this->assertSame(80.0, $data['assignment_stats'][0]['max']);
        $this->assertSame(60.0, $data['assignment_stats'][0]['min']);

        $students = $data['students']->keyBy(fn ($s) => $s['user']->name);
        $this->assertSame(80.0, $students['Alice']['avg_mark']);
        $this->assertSame(1, $students['Alice']['assessment_count']);
    }

    public function test_clo_attainment_uses_assessment_clo_mapping(): void
    {
        $alice = $this->enrol('Alice');

        $clo = CourseLearningOutcome::create([
            'course_id' => $this->course->id,
            'code' => 'CLO1',
            'description' => 'Analyse things',
        ]);

        $cs = $this->assessment('Case Study 2');
        $cs->clos()->attach($clo->id);
        $this->score($cs, $alice, 15.0); // 75%

        $data = $this->aggregator->getCoursePerformance($this->course);

        $this->assertSame('CLO1', $data['clo_attainment'][0]['code']);
        $this->assertSame(75.0, $data['clo_attainment'][0]['avg']);
        $this->assertSame(1, $data['clo_attainment'][0]['count']);
        $this->assertSame(1, $data['clo_attainment'][0]['student_count']);
    }

    /**
     * A parent CAP row aggregates its children; counting both would
     * double-weight the same work.
     */
    public function test_parent_assessment_scores_are_excluded(): void
    {
        $alice = $this->enrol('Alice');

        $parent = $this->assessment('Case Study', 20, 20);
        $child = $this->assessment('Report', 20, 20, $parent->id);

        $this->score($parent, $alice, 10.0); // 50% — must be ignored
        $this->score($child, $alice, 18.0);  // 90% — must count

        $data = $this->aggregator->getCoursePerformance($this->course);

        $this->assertCount(1, $data['assignment_stats']);
        $this->assertSame('Report', $data['assignment_stats'][0]['title']);
        $this->assertSame(90.0, $data['avg_mark']);
    }

    public function test_lecturer_sees_unreleased_scores_but_student_does_not(): void
    {
        $alice = $this->enrol('Alice');

        $released = $this->assessment('Test 1', 20, 20);
        $hidden = $this->assessment('Test 2', 20, 20);
        $this->score($released, $alice, 20.0, released: true); // 100%
        $this->score($hidden, $alice, 10.0, released: false);  // 50%

        // Lecturer view: both counted.
        $lecturerData = $this->aggregator->getStudentCoursePerformance($alice, $this->course);
        $this->assertCount(2, $lecturerData['marks']);
        $this->assertSame(75.0, $lecturerData['avg_mark']);

        // Student view: only the released one.
        $studentData = $this->aggregator->getStudentCoursePerformance($alice, $this->course, releasedOnly: true);
        $this->assertCount(1, $studentData['marks']);
        $this->assertSame(100.0, $studentData['avg_mark']);
        $this->assertSame('Test 1', $studentData['marks']->first()->title);
    }

    public function test_released_feedback_is_exposed_and_unreleased_is_withheld(): void
    {
        $alice = $this->enrol('Alice');

        $a = $this->assessment('Case Study 2');
        $this->score($a, $alice, 15.0, released: true)->update(['feedback' => 'Well argued.']);

        $b = $this->assessment('Case Study 3');
        $this->score($b, $alice, 15.0, released: false)->update(['feedback' => 'Secret note.']);

        $data = $this->aggregator->getStudentCoursePerformance($alice, $this->course);
        $items = $data['marks']->keyBy(fn ($i) => $i->title);

        $this->assertSame('Well argued.', $items['Case Study 2']->feedbackText);
        // Lecturer can see the row, but unreleased feedback is never surfaced.
        $this->assertNull($items['Case Study 3']->feedbackText);
    }

    public function test_per_student_clo_attainment_averages_that_students_mapped_assessments(): void
    {
        $alice = $this->enrol('Alice');
        $bob = $this->enrol('Bob');

        $clo1 = CourseLearningOutcome::create([
            'course_id' => $this->course->id, 'code' => 'CLO1', 'description' => 'Analyse',
        ]);
        $clo2 = CourseLearningOutcome::create([
            'course_id' => $this->course->id, 'code' => 'CLO2', 'description' => 'Evaluate',
        ]);

        $test = $this->assessment('Test 1');
        $test->clos()->attach($clo1->id);
        $report = $this->assessment('Report');
        $report->clos()->attach($clo1->id);
        $essay = $this->assessment('Essay');
        $essay->clos()->attach($clo2->id);

        // Alice: CLO1 from 90% and 70% -> 80%. CLO2 from 50%.
        $this->score($test, $alice, 18.0);
        $this->score($report, $alice, 14.0);
        $this->score($essay, $alice, 10.0);
        // Bob scores differently; must not bleed into Alice's attainment.
        $this->score($test, $bob, 4.0);
        $this->score($report, $bob, 4.0);
        $this->score($essay, $bob, 4.0);

        $data = $this->aggregator->getStudentCoursePerformance($alice, $this->course);
        $clos = collect($data['clo_attainment'])->keyBy('code');

        $this->assertSame(80.0, $clos['CLO1']['avg']);
        $this->assertSame(2, $clos['CLO1']['count']);
        $this->assertSame(50.0, $clos['CLO2']['avg']);
        $this->assertSame(1, $clos['CLO2']['count']);
    }

    /**
     * A CLO the student was never assessed on must report null, not 0 —
     * the views render "Not assessed" rather than a red 0% bar.
     */
    public function test_unassessed_clo_reports_null_not_zero(): void
    {
        $alice = $this->enrol('Alice');

        $graded = CourseLearningOutcome::create([
            'course_id' => $this->course->id, 'code' => 'CLO1', 'description' => 'Analyse',
        ]);
        CourseLearningOutcome::create([
            'course_id' => $this->course->id, 'code' => 'CLO2', 'description' => 'Never assessed',
        ]);

        $a = $this->assessment('Test 1');
        $a->clos()->attach($graded->id);
        $this->score($a, $alice, 16.0);

        $data = $this->aggregator->getStudentCoursePerformance($alice, $this->course);
        $clos = collect($data['clo_attainment'])->keyBy('code');

        $this->assertSame(80.0, $clos['CLO1']['avg']);
        $this->assertNull($clos['CLO2']['avg']);
        $this->assertSame(0, $clos['CLO2']['count']);
    }

    /**
     * An unreleased assessment must not contribute to a student's own CLO
     * attainment, or the score leaks through the CLO bar.
     */
    public function test_unreleased_assessment_excluded_from_student_clo_attainment(): void
    {
        $alice = $this->enrol('Alice');

        $clo = CourseLearningOutcome::create([
            'course_id' => $this->course->id, 'code' => 'CLO1', 'description' => 'Analyse',
        ]);

        $released = $this->assessment('Test 1');
        $released->clos()->attach($clo->id);
        $this->score($released, $alice, 18.0, released: true); // 90%

        $hidden = $this->assessment('Test 2');
        $hidden->clos()->attach($clo->id);
        $this->score($hidden, $alice, 2.0, released: false); // 10%

        $lecturer = $this->aggregator->getStudentCoursePerformance($alice, $this->course);
        $this->assertSame(50.0, collect($lecturer['clo_attainment'])->firstWhere('code', 'CLO1')['avg']);

        $student = $this->aggregator->getStudentCoursePerformance($alice, $this->course, releasedOnly: true);
        $this->assertSame(90.0, collect($student['clo_attainment'])->firstWhere('code', 'CLO1')['avg']);
    }

    public function test_course_with_no_grades_reports_null_average(): void
    {
        $this->enrol('Alice');

        $data = $this->aggregator->getCoursePerformance($this->course);

        $this->assertNull($data['avg_mark']);
        $this->assertTrue($data['assignment_stats']->isEmpty());
    }
}
