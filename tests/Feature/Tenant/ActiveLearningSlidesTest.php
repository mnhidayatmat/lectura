<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Teaching steps carry the lecture slides to teach, shown as thumbnails that
 * open full size.
 */
class ActiveLearningSlidesTest extends ApiTestCase
{
    public function test_plan_pages_show_the_slides_of_a_teaching_step(): void
    {
        Storage::fake('media');

        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $plan = ActiveLearningPlan::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'week_number' => 1,
            'title' => 'S1 – Piping Fundamentals & Codes',
            'created_by' => $lecturer->id,
        ]);

        $activity = ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 0,
            'title' => 'Teach Part 1 – the six code words',
            'type' => 'whole_class',
            'content_meta' => ['slides' => [
                ['number' => 10, 'title' => 'The six code words in one table', 'path' => 'active-learning/s1/slide-10.jpg'],
                ['number' => 11, 'title' => 'No path'],
            ]],
        ]);

        $this->assertCount(1, $activity->slides);
        $this->assertSame(10, $activity->slides[0]['number']);

        $base = "/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}";

        foreach ([$base, "{$base}/edit"] as $url) {
            $this->actingAs($lecturer)
                ->get($url)
                ->assertOk()
                ->assertSee('Slides to teach')
                ->assertSee('The six code words in one table')
                ->assertSee(Storage::disk('media')->url('active-learning/s1/slide-10.jpg'), false);
        }
    }

    public function test_plan_page_structures_the_description_and_lists_the_session_flow(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $plan = ActiveLearningPlan::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => 'S1',
            'description' => "Session 1 of 7.\n\nPairs and teams\n- 6 pairs\n- 3 teams of 4",
            'created_by' => $lecturer->id,
        ]);

        $welcome = ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 0,
            'title' => 'Teach 0 – Welcome',
            'type' => 'whole_class',
            'duration_minutes' => 70,
            'content_meta' => ['slides' => [
                ['number' => 1, 'title' => 'Title', 'path' => 'a/slide-01.jpg'],
                ['number' => 7, 'title' => 'Assessment', 'path' => 'a/slide-07.jpg'],
            ]],
        ]);

        ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 1,
            'title' => 'Mini 1 – Pair warm-up',
            'type' => 'pair',
            'duration_minutes' => 6,
        ]);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}")
            ->assertOk()
            ->assertSee('<ul><li>6 pairs</li><li>3 teams of 4</li></ul>', false)
            ->assertSee('Session flow')
            ->assertSeeInOrder(['0:00', 'Teach 0 – Welcome', 'Slides 1–7', '1:10', 'Mini 1 – Pair warm-up'])
            ->assertSee('href="#activity-'.$welcome->id.'"', false)
            ->assertSee('id="activity-'.$welcome->id.'"', false);
    }

    public function test_activities_without_slides_show_no_slide_strip(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $plan = ActiveLearningPlan::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => 'Plain plan',
            'created_by' => $lecturer->id,
        ]);

        ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'title' => 'Think–Pair–Share',
            'type' => 'pair',
        ]);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}")
            ->assertOk()
            ->assertDontSee('Slides to teach');
    }

    public function test_lecturer_can_reorder_activities_from_the_plan_pages(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $plan = ActiveLearningPlan::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => 'S1',
            'created_by' => $lecturer->id,
        ]);

        [$first, $second, $third] = collect(['Teach', 'Mini', 'Core'])->map(fn ($title, $i) => ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => $i,
            'title' => $title,
            'type' => 'pair',
        ]))->all();

        $otherPlan = ActiveLearningPlan::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => 'Other',
            'created_by' => $lecturer->id,
        ]);
        $foreign = ActiveLearningActivity::create([
            'active_learning_plan_id' => $otherPlan->id,
            'sort_order' => 5,
            'title' => 'Elsewhere',
            'type' => 'pair',
        ]);

        $base = "/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}";

        foreach ([$base, "{$base}/edit"] as $url) {
            $this->actingAs($lecturer)
                ->get($url)
                ->assertOk()
                ->assertSee('activitySorter', false)
                ->assertSee('data-activity-id="'.$first->id.'"', false)
                ->assertSee('Move up');
        }

        $this->actingAs($lecturer)
            ->postJson("{$base}/activities/reorder", ['ordered_ids' => [$third->id, $first->id, $foreign->id, $second->id]])
            ->assertOk();

        $this->assertSame(['Core', 'Teach', 'Mini'], $plan->activities()->pluck('title')->all());
        $this->assertSame(5, $foreign->fresh()->sort_order);
    }

    public function test_another_lecturer_cannot_reorder_the_plan(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $stranger = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner);

        $plan = ActiveLearningPlan::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => 'S1',
            'created_by' => $owner->id,
        ]);
        $activity = ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 3,
            'title' => 'Teach',
            'type' => 'pair',
        ]);

        $this->actingAs($stranger)
            ->postJson("/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}/activities/reorder", ['ordered_ids' => [$activity->id]])
            ->assertForbidden();

        $this->assertSame(3, $activity->fresh()->sort_order);
    }
}
