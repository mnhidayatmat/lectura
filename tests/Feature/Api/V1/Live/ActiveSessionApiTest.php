<?php

namespace Tests\Feature\Api\V1\Live;

use App\Events\ActiveLearning\ResponseSubmitted;
use App\Models\ActiveLearningGroup;
use App\Models\ActiveLearningGroupMember;
use App\Models\ActiveLearningResponse;
use App\Models\ActiveLearningSession;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Live\Concerns\BuildsLiveFixtures;

class ActiveSessionApiTest extends ApiTestCase
{
    use BuildsLiveFixtures;

    public function test_show_joins_the_active_session(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $session = $this->createLiveSession($section->course, $lecturer);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/sessions/{$session->id}"))
            ->assertOk()
            ->assertJsonPath('data.type', 'session')
            ->assertJsonPath('data.title', 'Week 5: Derivatives')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.joined', true)
            ->assertJsonPath('data.total_activities', 3)
            ->assertJsonPath('data.has_review', false);

        $this->assertDatabaseHas('active_learning_session_participants', ['session_id' => $session->id, 'user_id' => $student->id]);
    }

    public function test_not_enrolled_student_is_forbidden(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $outsider = $this->createMember($tenant, 'student');
        $session = $this->createLiveSession($section->course, $lecturer);

        foreach (['', '/state', '/review'] as $suffix) {
            $this->actingAsApi($outsider)->getJson($this->tenantApi($tenant, "live/sessions/{$session->id}{$suffix}"))
                ->assertForbidden()
                ->assertJsonPath('message', 'You are not enrolled in this course.');
        }
    }

    public function test_session_from_another_institution_is_not_found(): void
    {
        [, $lecturer, , $section] = $this->classroom();
        $session = $this->createLiveSession($section->course, $lecturer);

        $otherTenant = $this->createTenant();
        $otherStudent = $this->createMember($otherTenant, 'student');

        $this->actingAsApi($otherStudent)->getJson($this->tenantApi($otherTenant, "live/sessions/{$session->id}/state"))
            ->assertNotFound();
    }

    public function test_not_started_session_is_not_found(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $session = $this->createLiveSession($section->course, $lecturer, ActiveLearningSession::STATUS_NOT_STARTED);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/sessions/{$session->id}"))->assertNotFound();
    }

    public function test_state_shows_the_current_activity_without_answers(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $session = $this->createLiveSession($section->course, $lecturer);

        $response = $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/sessions/{$session->id}/state"))
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.responses_open', true)
            ->assertJsonPath('data.current_index', 1)
            ->assertJsonPath('data.total_activities', 3)
            ->assertJsonPath('data.current_activity.title', 'Warm-up poll')
            ->assertJsonPath('data.current_activity.number', 1)
            ->assertJsonPath('data.current_activity.type_label', 'Individual')
            ->assertJsonPath('data.current_activity.response_type', 'mcq')
            ->assertJsonPath('data.current_activity.multi_select', false)
            ->assertJsonPath('data.current_activity.instructions_text', 'Which rule applies to sin(x²)?')
            ->assertJsonCount(3, 'data.current_activity.poll_options')
            ->assertJsonPath('data.current_activity.my_response', null)
            ->assertJsonPath('data.current_activity.my_group', null);

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertStringNotContainsString('solution', $response->getContent());
        $this->assertLessThanOrEqual(300, $response->json('data.current_activity.time_remaining_seconds'));
    }

    public function test_student_submits_and_updates_a_poll_response(): void
    {
        Event::fake([ResponseSubmitted::class]);

        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $session = $this->createLiveSession($section->course, $lecturer);
        $optionIds = $session->currentActivity->pollOptions()->pluck('id');
        $url = $this->tenantApi($tenant, "live/sessions/{$session->id}/respond");

        $this->actingAsApi($student)->postJson($url, [
            'activity_id' => $session->current_activity_id,
            'response_data' => ['selected_options' => [$optionIds[1]]],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Response submitted!')
            ->assertJsonPath('data.selected_option_ids', [$optionIds[1]]);

        $this->postJson($url, [
            'activity_id' => $session->current_activity_id,
            'response_data' => ['selected_options' => [$optionIds[0]]],
        ])->assertOk();

        $this->assertSame(1, ActiveLearningResponse::count());
        Event::assertDispatched(ResponseSubmitted::class, 2);

        $this->getJson($this->tenantApi($tenant, "live/sessions/{$session->id}/state"))
            ->assertJsonPath('data.participant_count', 1)
            ->assertJsonPath('data.current_activity.response_count', 1)
            ->assertJsonPath('data.current_activity.my_response.selected_option_ids', [$optionIds[0]]);
    }

    public function test_response_validation(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $session = $this->createLiveSession($section->course, $lecturer);
        $activities = $session->plan->activities()->get();
        $optionIds = $activities[0]->pollOptions()->pluck('id');
        $url = $this->tenantApi($tenant, "live/sessions/{$session->id}/respond");

        $this->actingAsApi($student)->postJson($url, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['activity_id', 'response_data']);

        $this->postJson($url, [
            'activity_id' => $activities[0]->id,
            'response_data' => ['selected_options' => [$optionIds[0], $optionIds[1]]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['response_data.selected_options' => ['Please select only one option.']]);

        $this->postJson($url, [
            'activity_id' => $activities[0]->id,
            'response_data' => ['selected_options' => [999999]],
        ])->assertStatus(422)->assertJsonValidationErrors('response_data.selected_options');

        $this->postJson($url, [
            'activity_id' => $activities[1]->id,
            'response_data' => ['text' => 'Too early'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This activity is not currently active.');

        $session->update(['current_activity_id' => $activities[1]->id]);

        $this->postJson($url, [
            'activity_id' => $activities[1]->id,
            'response_data' => ['text' => '   '],
        ])->assertStatus(422)->assertJsonValidationErrors('response_data.text');

        $this->postJson($url, [
            'activity_id' => $activities[1]->id,
            'response_data' => ['text' => 'Differentiate the outer function, then multiply by the inner derivative.'],
        ])
            ->assertOk()
            ->assertJsonPath('data.text', 'Differentiate the outer function, then multiply by the inner derivative.');
    }

    public function test_review_is_available_after_the_session_completes(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $session = $this->createLiveSession($section->course, $lecturer);
        $optionId = $session->currentActivity->pollOptions()->value('id');

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "live/sessions/{$session->id}/respond"), [
            'activity_id' => $session->current_activity_id,
            'response_data' => ['selected_options' => [$optionId]],
        ])->assertOk();

        $this->getJson($this->tenantApi($tenant, "live/sessions/{$session->id}/review"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This session is still in progress.');

        $session->update(['status' => ActiveLearningSession::STATUS_COMPLETED, 'current_activity_id' => null, 'ended_at' => now()]);

        $this->getJson($this->tenantApi($tenant, "live/sessions/{$session->id}/state"))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.current_activity', null)
            ->assertJsonPath('data.responses_open', false);

        $response = $this->getJson($this->tenantApi($tenant, "live/sessions/{$session->id}/review"))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.responded_count', 1)
            ->assertJsonCount(3, 'data.activities')
            ->assertJsonPath('data.activities.0.my_response.selected_option_ids', [$optionId])
            ->assertJsonPath('data.activities.1.my_response', null)
            ->assertJsonPath('data.activities.2.max_length', 500);

        $this->assertStringNotContainsString('is_correct', $response->getContent());

        $this->getJson($this->tenantApi($tenant, 'live/hub'))
            ->assertJsonCount(1, 'data.recent_sessions')
            ->assertJsonPath('data.recent_sessions.0.joined', true);
    }

    public function test_state_shows_my_group_with_members(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $partner = $this->createMember($tenant, 'student', ['name' => 'Hafiz Rahman']);
        $this->enroll($section, $partner);
        $session = $this->createLiveSession($section->course, $lecturer);

        $group = ActiveLearningGroup::create([
            'active_learning_activity_id' => $session->current_activity_id,
            'name' => 'Group A',
            'sort_order' => 0,
        ]);
        ActiveLearningGroupMember::create(['active_learning_group_id' => $group->id, 'user_id' => $student->id, 'role' => 'facilitator']);
        ActiveLearningGroupMember::create(['active_learning_group_id' => $group->id, 'user_id' => $partner->id, 'role' => 'member']);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/sessions/{$session->id}/state"))
            ->assertOk()
            ->assertJsonPath('data.current_activity.my_group.name', 'Group A')
            ->assertJsonCount(2, 'data.current_activity.my_group.members')
            ->assertJsonFragment(['name' => 'Hafiz Rahman', 'role' => 'member', 'is_me' => false]);
    }
}
