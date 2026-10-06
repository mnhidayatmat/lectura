<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\ActiveLearningSession;
use App\Services\ActiveLearning\SessionService;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The live session screens render activity instructions as formatted,
 * sanitised HTML rather than raw tags.
 */
class ActiveLearningSessionInstructionsTest extends ApiTestCase
{
    public function test_session_state_carries_sanitised_instruction_html(): void
    {
        [$session, $activity] = $this->liveSession([
            'instructions' => '<h4>Slides 1–3</h4><ul><li><strong>Slide 1</strong></li></ul><script>alert(1)</script>',
        ]);

        $html = app(SessionService::class)->getSessionState($session)['current_activity']['instructions_html'];

        $this->assertStringContainsString('<h4>Slides 1–3</h4><ul><li><strong>Slide 1</strong></li></ul>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_plain_text_description_is_escaped_when_there_are_no_instructions(): void
    {
        [$session] = $this->liveSession([
            'instructions' => null,
            'description' => "Keep P < 5 bar & discuss\n- one\n- two",
        ]);

        $html = app(SessionService::class)->getSessionState($session)['current_activity']['instructions_html'];

        $this->assertSame('<p>Keep P &lt; 5 bar &amp; discuss</p><ul><li>one</li><li>two</li></ul>', $html);
    }

    public function test_review_page_renders_instructions_as_html(): void
    {
        [$session, , $tenant, $lecturer, $course, $plan] = $this->liveSession([
            'instructions' => '<ul><li><strong>Slide 1</strong></li></ul>',
        ], ActiveLearningSession::STATUS_COMPLETED);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/session/{$session->id}/review")
            ->assertOk()
            ->assertSee('<ul><li><strong>Slide 1</strong></li></ul>', false)
            ->assertDontSee('&lt;strong&gt;', false);
    }

    private function liveSession(array $activityAttributes, string $status = ActiveLearningSession::STATUS_ACTIVE): array
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $plan = ActiveLearningPlan::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => 'S1',
            'status' => 'published',
            'created_by' => $lecturer->id,
        ]);

        $activity = ActiveLearningActivity::create(array_merge([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 0,
            'title' => 'Teach 0',
            'type' => 'whole_class',
        ], $activityAttributes));

        $session = ActiveLearningSession::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'current_activity_id' => $activity->id,
            'started_at' => now(),
            'ended_at' => $status === ActiveLearningSession::STATUS_COMPLETED ? now() : null,
            'created_by' => $lecturer->id,
        ]);

        return [$session, $activity, $tenant, $lecturer, $course, $plan];
    }
}
