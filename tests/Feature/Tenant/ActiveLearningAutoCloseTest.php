<?php

namespace Tests\Feature\Tenant;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\ActiveLearningSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Live active-learning sessions end themselves after the time limit.
 */
class ActiveLearningAutoCloseTest extends ApiTestCase
{
    private function liveSession(Tenant $tenant, User $lecturer, array $attributes = []): ActiveLearningSession
    {
        $course = $this->createCourse($tenant, $lecturer);
        $plan = ActiveLearningPlan::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => 'S1 – Piping Fundamentals & Codes',
            'status' => 'published',
            'created_by' => $lecturer->id,
        ]);
        $activity = ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 0,
            'title' => 'Teach 0',
            'type' => 'whole_class',
        ]);

        return ActiveLearningSession::create(array_merge([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => ActiveLearningSession::STATUS_ACTIVE,
            'current_activity_id' => $activity->id,
            'started_at' => now(),
            'created_by' => $lecturer->id,
        ], $attributes));
    }

    public function test_the_scheduled_command_ends_sessions_past_the_limit(): void
    {
        config(['lectura.active_learning.auto_close_hours' => 4]);
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $stale = $this->liveSession($tenant, $lecturer, ['started_at' => now()->subDays(8)]);
        $fresh = $this->liveSession($tenant, $lecturer, ['started_at' => now()->subHours(3)]);

        $this->artisan('active-learning:close-stale')->expectsOutput('1 session(s) closed.')->assertSuccessful();

        $stale->refresh();
        $this->assertTrue($stale->isCompleted());
        $this->assertNull($stale->current_activity_id);
        $this->assertTrue($stale->summary_data['auto_closed']);
        // It ends at its time limit, not eight days later when it was noticed.
        $this->assertSame($stale->started_at->copy()->addHours(4)->toDateTimeString(), $stale->ended_at->toDateTimeString());
        $this->assertTrue($fresh->fresh()->isActive());
    }

    public function test_ordinary_requests_close_stale_sessions_without_cron_across_institutions(): void
    {
        config(['lectura.active_learning.auto_close_hours' => 4]);
        Cache::flush();
        $other = $this->createTenant();
        $stale = $this->liveSession($other, $this->createMember($other, 'lecturer'), ['started_at' => now()->subHours(5)]);

        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/dashboard'))->assertOk();

        $this->assertTrue($stale->fresh()->isCompleted());
    }

    public function test_the_sweep_runs_at_most_once_a_minute(): void
    {
        config(['lectura.active_learning.auto_close_hours' => 4]);
        Cache::flush();
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/dashboard'))->assertOk();
        $stale = $this->liveSession($tenant, $this->createMember($tenant, 'lecturer'), ['started_at' => now()->subHours(6)]);
        $this->getJson($this->tenantApi($tenant, 'student/dashboard'))->assertOk();
        $this->assertTrue($stale->fresh()->isActive());

        $this->travel(61)->seconds();
        $this->getJson($this->tenantApi($tenant, 'student/dashboard'))->assertOk();
        $this->assertTrue($stale->fresh()->isCompleted());
    }

    public function test_zero_hours_turns_auto_close_off(): void
    {
        config(['lectura.active_learning.auto_close_hours' => 0]);
        $tenant = $this->createTenant();
        $session = $this->liveSession($tenant, $this->createMember($tenant, 'lecturer'), ['started_at' => now()->subDays(30)]);

        $this->artisan('active-learning:close-stale')->expectsOutput('0 session(s) closed.');

        $this->assertTrue($session->fresh()->isActive());
        $this->assertNull($session->autoCloseAt());
    }

    public function test_dashboard_tells_the_lecturer_when_it_closes(): void
    {
        config(['lectura.active_learning.auto_close_hours' => 4]);
        $tenant = $this->createTenant(['timezone' => 'Asia/Kuala_Lumpur']);
        $lecturer = $this->createMember($tenant, 'lecturer');
        $this->travelTo(now()->setTimezone('UTC')->setTime(8, 25));
        $session = $this->liveSession($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$session->plan->course_id}/active-learning/{$session->plan_id}/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('Closes automatically at 8:25 PM');
    }
}
