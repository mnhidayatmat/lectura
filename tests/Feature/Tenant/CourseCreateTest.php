<?php

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\CourseTopic;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Creating a course from the web form, and linking its weekly topics to CLOs.
 */
class CourseCreateTest extends ApiTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'BTG3333',
            'title' => 'Piping',
            'num_weeks' => 3,
            'teaching_mode' => 'face_to_face',
            'clos' => [
                ['code' => 'CO1', 'description' => 'Distinguish piping components.'],
                ['code' => 'CO2', 'description' => 'Evaluate pipe wall thickness.'],
                ['code' => '', 'description' => ''],
            ],
            'topics' => [
                ['week_number' => 1, 'title' => 'Introduction to Piping System', 'description' => "1.1 Definition\n1.2 Components", 'clos' => ['CO1']],
                ['week_number' => 2, 'title' => 'Load and Stress', 'clos' => ['CO1', 'CO2']],
                ['week_number' => 3, 'title' => ''],
            ],
        ], $overrides);
    }

    public function test_blank_rows_are_skipped_and_weeks_are_linked_to_clos(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/courses", $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $course = Course::withoutGlobalScopes()->where('code', 'BTG3333')->firstOrFail();
        $co1 = CourseLearningOutcome::where('course_id', $course->id)->where('code', 'CO1')->value('id');
        $co2 = CourseLearningOutcome::where('course_id', $course->id)->where('code', 'CO2')->value('id');

        $this->assertSame(2, CourseLearningOutcome::where('course_id', $course->id)->count());
        $this->assertSame(2, CourseTopic::where('course_id', $course->id)->count());

        $week1 = CourseTopic::where('course_id', $course->id)->where('week_number', 1)->first();
        $this->assertSame([$co1], $week1->clo_ids);
        $this->assertSame("1.1 Definition\n1.2 Components", $week1->description);
        $this->assertSame([$co1, $co2], CourseTopic::where('course_id', $course->id)->where('week_number', 2)->value('clo_ids'));
    }

    public function test_a_failed_save_keeps_the_typed_clos_and_weeks(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $payload = $this->payload();
        $payload['topics'][0]['title'] = str_repeat('x', 256);

        $this->actingAs($lecturer)
            ->from("/{$tenant->slug}/courses/create")
            ->post("/{$tenant->slug}/courses", $payload)
            ->assertRedirect("/{$tenant->slug}/courses/create")
            ->assertSessionHasErrors('topics.0.title');

        $this->actingAs($lecturer)
            ->from("/{$tenant->slug}/courses/create")
            ->followingRedirects()
            ->post("/{$tenant->slug}/courses", $payload)
            ->assertOk()
            ->assertSee('The course was not saved.')
            ->assertSee('Distinguish piping components.')
            ->assertSee('Load and Stress');

        $this->assertNull(Course::withoutGlobalScopes()->where('code', 'BTG3333')->first());
    }

    public function test_the_course_page_groups_sections_by_semester_newest_first(): void
    {
        $this->travelTo('2026-09-23');

        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $older = $this->createTerm($tenant, ['name' => 'Semester 1, 2025/2026', 'start_date' => '2025-10-01', 'end_date' => '2026-02-28']);
        $newer = $this->createTerm($tenant, ['name' => 'Semester 2, 2026/2027', 'start_date' => '2027-03-01', 'end_date' => '2027-07-31']);

        $this->createSection($course, ['name' => 'Old Section A', 'code' => 'A', 'academic_term_id' => $older->id]);
        $this->createSection($course, ['name' => 'New Section B', 'code' => 'B', 'academic_term_id' => $newer->id]);
        $this->createSection($course, ['name' => 'Loose Section C', 'code' => 'C']);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Semester 2, 2026/2027', 'New Section B',
                'Semester 1, 2025/2026', 'Old Section A',
                'No semester', 'Loose Section C',
            ])
            ->assertSee('Semester 2, 2026/2027 starts 1 Mar 2027');
    }

    public function test_a_weeks_clos_can_be_changed_but_only_to_the_courses_own_clos(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $clo = CourseLearningOutcome::create(['course_id' => $course->id, 'code' => 'CO1', 'description' => 'x', 'sort_order' => 0]);
        $foreign = CourseLearningOutcome::create([
            'course_id' => $this->createCourse($tenant, $lecturer)->id,
            'code' => 'CO9', 'description' => 'y', 'sort_order' => 0,
        ]);
        $topic = CourseTopic::create(['course_id' => $course->id, 'week_number' => 1, 'title' => 'Intro', 'sort_order' => 1]);

        $this->actingAs($lecturer)
            ->put("/{$tenant->slug}/courses/{$course->id}/topics/{$topic->id}", ['clo_ids' => [$clo->id, $foreign->id]])
            ->assertSessionHas('success');

        $this->assertSame([$clo->id], $topic->fresh()->clo_ids);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}")
            ->assertOk()
            ->assertSee('Edit CLOs');

        $student = $this->createMember($tenant, 'student');

        $this->actingAs($student)
            ->put("/{$tenant->slug}/courses/{$course->id}/topics/{$topic->id}", ['clo_ids' => []])
            ->assertForbidden();

        $this->actingAs($student)
            ->delete("/{$tenant->slug}/courses/{$course->id}/topics/{$topic->id}")
            ->assertForbidden();

        $this->assertSame([$clo->id], $topic->fresh()->clo_ids);
    }
}
