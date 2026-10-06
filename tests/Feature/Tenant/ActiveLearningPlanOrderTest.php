<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\ActiveLearningPlan;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Lecturers arrange their plan cards by hand; that order is the default view.
 */
class ActiveLearningPlanOrderTest extends ApiTestCase
{
    public function test_plan_list_defaults_to_the_manual_order(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $this->createPlan($course, $lecturer, 'Gamma', ['sort_order' => 2]);
        $this->createPlan($course, $lecturer, 'Alpha', ['sort_order' => 0]);
        $this->createPlan($course, $lecturer, 'Beta', ['sort_order' => 1]);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}/active-learning")
            ->assertOk()
            ->assertSee('listSorter', false)
            ->assertSee('Manual order')
            ->assertSee('Move up')
            ->assertSeeInOrder(['Alpha', 'Beta', 'Gamma']);
    }

    public function test_other_sorts_hide_the_arrange_controls(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $this->createPlan($course, $lecturer, 'Alpha');
        $this->createPlan($course, $lecturer, 'Beta');

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}/active-learning?sort=title_desc")
            ->assertOk()
            ->assertSeeInOrder(['Beta', 'Alpha'])
            ->assertDontSee('listSorter', false)
            ->assertDontSee('Move up')
            ->assertSee('Arrange manually');
    }

    public function test_new_plans_join_the_end_of_the_order(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $other = $this->createCourse($tenant, $lecturer);

        $this->createPlan($course, $lecturer, 'First', ['sort_order' => 4]);
        $this->createPlan($other, $lecturer, 'Elsewhere', ['sort_order' => 9]);

        $this->assertSame(5, $this->createPlan($course, $lecturer, 'Second')->sort_order);
        $this->assertSame(0, $this->createPlan($this->createCourse($tenant, $lecturer), $lecturer, 'Only')->sort_order);
    }

    public function test_lecturer_saves_a_new_order_and_foreign_plans_are_ignored(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $other = $this->createCourse($tenant, $lecturer);
        $a = $this->createPlan($course, $lecturer, 'A');
        $b = $this->createPlan($course, $lecturer, 'B');
        $c = $this->createPlan($course, $lecturer, 'C');
        $foreign = $this->createPlan($other, $lecturer, 'Foreign', ['sort_order' => 7]);

        $this->actingAs($lecturer)
            ->postJson("/{$tenant->slug}/courses/{$course->id}/active-learning/reorder", ['ordered_ids' => [$c->id, $foreign->id, $a->id, $b->id]])
            ->assertOk();

        $this->assertSame(
            ['C', 'A', 'B'],
            ActiveLearningPlan::where('course_id', $course->id)->orderBy('sort_order')->pluck('title')->all()
        );
        $this->assertSame(7, $foreign->fresh()->sort_order);
    }

    public function test_another_lecturer_cannot_reorder_the_plans(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $stranger = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner);
        $plan = $this->createPlan($course, $owner, 'A', ['sort_order' => 3]);

        $this->actingAs($stranger)
            ->postJson("/{$tenant->slug}/courses/{$course->id}/active-learning/reorder", ['ordered_ids' => [$plan->id]])
            ->assertForbidden();

        $this->assertSame(3, $plan->fresh()->sort_order);
    }

    public function test_migration_backfills_week_order_then_creation_order(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $noWeek = $this->createPlan($course, $lecturer, 'No week');
        $week2 = $this->createPlan($course, $lecturer, 'Week 2', ['week_number' => 2]);
        $week1 = $this->createPlan($course, $lecturer, 'Week 1', ['week_number' => 1]);
        DB::table('active_learning_plans')->update(['sort_order' => null]);

        $migration = require database_path('migrations/2026_09_27_000002_add_sort_order_to_active_learning_plans_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame(
            [$week1->id, $week2->id, $noWeek->id],
            ActiveLearningPlan::where('course_id', $course->id)->orderBy('sort_order')->pluck('id')->all()
        );
    }

    private function createPlan(Course $course, User $lecturer, string $title, array $attributes = []): ActiveLearningPlan
    {
        return ActiveLearningPlan::create(array_merge([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'title' => $title,
            'created_by' => $lecturer->id,
        ], $attributes));
    }
}
