<?php

namespace Tests\Feature\Tenant;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AssessmentPublished;
use App\Services\Assessment\AssessmentAnnouncer;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Assessment rosters list this semester's class only: students of a finished
 * section (last semester's, still enrolled) stay out. A course that only ran in a
 * past semester keeps all its students, so its marks can still be opened.
 */
class AssessmentCurrentSemesterRosterTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-25 10:00:00');
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Course, 3: Assessment}
     */
    private function courseWithPastAndCurrentSections(): array
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $past = $this->createTerm($tenant, ['name' => 'Semester 2, 2025/2026', 'start_date' => '2026-03-09', 'end_date' => '2026-06-19']);
        $current = $this->createTerm($tenant, ['start_date' => '2026-09-28', 'end_date' => '2027-01-08']);
        $course = $this->createCourse($tenant, $lecturer, ['academic_term_id' => $current->id]);

        $old = $this->createSection($course, ['academic_term_id' => $past->id, 'is_active' => false]);
        $now = $this->createSection($course, ['academic_term_id' => $current->id, 'code' => '02']);
        $this->enroll($old, $this->createMember($tenant, 'student', ['name' => 'Graduated Student']));
        $this->enroll($now, $this->createMember($tenant, 'student', ['name' => 'Current Student']));

        $assessment = Assessment::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => 'Case Study 1',
            'type' => 'assignment',
            'weightage' => 10,
            'total_marks' => 100,
            'status' => 'active',
            'requires_submission' => true,
        ]);

        return [$tenant, $lecturer, $course, $assessment];
    }

    public function test_web_marking_pages_list_only_this_semesters_students(): void
    {
        [$tenant, $lecturer, $course, $assessment] = $this->courseWithPastAndCurrentSections();
        $base = "/{$tenant->slug}/courses/{$course->id}/assessments/{$assessment->id}";

        foreach (['/scores', '/scores/manual', '/submissions'] as $page) {
            $this->actingAs($lecturer)->get($base.$page)->assertOk()
                ->assertSee('Current Student')
                ->assertDontSee('Graduated Student');
        }
    }

    public function test_submission_counts_leave_out_last_semesters_submissions(): void
    {
        [$tenant, $lecturer, $course, $assessment] = $this->courseWithPastAndCurrentSections();
        foreach (['Graduated Student', 'Current Student'] as $name) {
            $assessment->submissions()->create([
                'tenant_id' => $tenant->id,
                'user_id' => User::where('name', $name)->value('id'),
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        }

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}/assessments/{$assessment->id}/submissions")
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['enrolled'] === 1 && $stats['submitted'] === 1)
            ->assertViewHas('submissions', fn ($submissions) => $submissions->count() === 1);
    }

    public function test_scores_counts_and_release_leave_out_last_semesters_scores(): void
    {
        [$tenant, $lecturer, $course, $assessment] = $this->courseWithPastAndCurrentSections();
        foreach (['Graduated Student' => 40, 'Current Student' => 80] as $name => $percentage) {
            $assessment->scores()->create([
                'tenant_id' => $tenant->id,
                'user_id' => User::where('name', $name)->value('id'),
                'raw_marks' => $percentage,
                'max_marks' => 100,
                'weighted_marks' => $percentage / 10,
                'percentage' => $percentage,
                'finalized_at' => now(),
            ]);
        }
        $base = "/{$tenant->slug}/courses/{$course->id}/assessments/{$assessment->id}";

        $this->actingAs($lecturer)->get($base.'/scores')->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['graded'] === 1 && (float) $stats['avg'] === 80.0);

        $this->actingAs($lecturer)->post($base.'/scores/release')->assertRedirect();
        $released = $assessment->scores()->with('user')->get()->mapWithKeys(fn ($s) => [$s->user->name => (bool) $s->is_released]);
        $this->assertSame(['Current Student' => true, 'Graduated Student' => false], $released->sortKeys()->all());

        $this->assertSame(1, $course->fresh()->totalStudents());
    }

    public function test_app_marking_lists_only_this_semesters_students(): void
    {
        [$tenant, $lecturer, $course, $assessment] = $this->courseWithPastAndCurrentSections();

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/assessments"))
            ->assertOk()
            ->assertJsonPath('meta.students', 1);

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, "lecturer/assessments/{$assessment->id}"))
            ->assertOk()
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.student.name', 'Current Student');
    }

    public function test_a_past_course_keeps_all_its_students(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $past = $this->createTerm($tenant, ['start_date' => '2026-03-09', 'end_date' => '2026-06-19']);
        $course = $this->createCourse($tenant, $lecturer, ['academic_term_id' => $past->id]);
        $closed = $this->createSection($course, ['academic_term_id' => $past->id, 'is_active' => false]);
        $open = $this->createSection($course, ['academic_term_id' => $past->id, 'code' => '02']);
        $this->enroll($closed, $this->createMember($tenant));
        $this->enroll($open, $this->createMember($tenant));

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/assessments"))
            ->assertOk()
            ->assertJsonPath('meta.students', 2);
    }

    public function test_publishing_notifies_only_this_semesters_students(): void
    {
        Notification::fake();
        [, , , $assessment] = $this->courseWithPastAndCurrentSections();

        $assessment->forceFill(['notify_students' => true])->save();
        app(AssessmentAnnouncer::class)->announceIfReleased($assessment->fresh());

        Notification::assertSentTo(User::where('name', 'Current Student')->first(), AssessmentPublished::class);
        Notification::assertNotSentTo(User::where('name', 'Graduated Student')->first(), AssessmentPublished::class);
    }
}
