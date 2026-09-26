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
}
