<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Live;

use App\Models\Course;
use App\Models\Tenant;
use App\Models\TenderGame;
use App\Models\TenderSession;
use App\Models\User;
use App\Services\Tender\TenderFlow;
use Tests\Feature\Api\V1\ApiTestCase;

class TenderApiTest extends ApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private TenderGame $game;

    private User $leader;

    /** @var array<string, array<int, list<User>>> company => group => students */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'BTG3333']);
        $this->game = $this->makeGame($course);
    }

    private function makeGame(Course $course): TenderGame
    {
        app()->instance('current_tenant', $this->tenant);
        $game = app(TenderFlow::class)->createGame([
            'tenant_id' => $this->tenant->id,
            'course_id' => $course->id,
            'title' => 'Project TERATAI',
        ]);
        app()->forgetInstance('current_tenant');

        $this->leader = $this->createMember($this->tenant);
        $game->members()->create(['user_id' => $this->leader->id, 'role' => 'leader']);

        foreach (['A', 'B'] as $company) {
            foreach ([1, 2] as $group) {
                for ($i = 0; $i < 3; $i++) {
                    $user = $this->createMember($this->tenant);
                    $game->members()->create([
                        'user_id' => $user->id,
                        'company' => $company,
                        'group_no' => $group,
                        'is_md' => $group === 1 && $i === 0,
                    ]);
                    $this->students[$company][$group][] = $user;
                }
            }
        }

        return $game;
    }

    private function s1(): TenderSession
    {
        return $this->game->sessions()->where('code', 'S1')->first();
    }

    private function url(string $path): string
    {
        return $this->tenantApi($this->tenant, 'live/tender/'.ltrim($path, '/'));
    }

    /** Group 1 of each company presents, so the Judges come from group 2. */
    private function startS1(): TenderSession
    {
        $session = $this->s1();

        $this->actingAsApi($this->leader)
            ->patchJson($this->url("sessions/{$session->id}"), [
                'first_company' => 'A', 'lead_group_a' => 1, 'lead_group_b' => 1, 'change_order_note' => 'CO-1',
            ])->assertOk();

        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 1, 'user_id' => $this->students['A'][2][0]->id])->assertOk();
        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 2, 'user_id' => $this->students['B'][2][0]->id])->assertOk();
        $this->postJson($this->url("sessions/{$session->id}/start"))->assertOk()->assertJsonPath('data.status', 'scoring');

        return $session;
    }

    /** @param array<string, array{0: int, 1: int}> $levels criterion => [A, B] */
    private function judgeScores(User $judge, TenderSession $session, array $levels): void
    {
        $this->actingAsApi($judge)->postJson($this->url("sessions/{$session->id}/declare"), ['conflict' => false])->assertOk();

        $scores = [];
        foreach ($levels as $criterion => [$a, $b]) {
            $scores[] = ['criterion' => $criterion, 'level_a' => $a, 'level_b' => $b, 'evidence' => "Seen on slide for {$criterion}"];
        }

        $this->putJson($this->url("sessions/{$session->id}/card"), ['scores' => $scores])->assertOk();
        $this->postJson($this->url("sessions/{$session->id}/card/submit"))->assertOk();
    }

    public function test_a_full_session_compiles_marks_and_announces_the_winner(): void
    {
        $session = $this->startS1();
        $j1 = $this->students['A'][2][0];
        $j2 = $this->students['B'][2][0];

        $this->judgeScores($j1, $session, [
            'intro' => [4, 3], 'plant_3d' => [3, 3], 'compliance' => [4, 2], 'change_order' => [3, 3], 'defence' => [3, 2],
        ]);

        // The Leader sees that Judge 1 is in, but no levels until both cards are.
        $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertOk()
            ->assertJsonPath('data.status', 'scoring')
            ->assertJsonPath('data.leader.sheet', null)
            ->assertJsonPath('data.leader.judges.0.submitted', true)
            ->assertJsonPath('data.leader.judges.1.submitted', false);

        $this->judgeScores($j2, $session, [
            'intro' => [3, 3], 'plant_3d' => [3, 2], 'compliance' => [4, 3], 'change_order' => [3, 3], 'defence' => [3, 3],
        ]);

        $response = $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertOk()
            ->assertJsonPath('data.status', 'compiled');

        // (4 + 3) ÷ 8 × 35 = 30.625 for Company A on Introduction.
        $this->assertEqualsWithDelta(30.63, $response->json('data.leader.sheet.criteria.0.a.mark'), 0.01);
        // A: 30.625 + 26.25 + 10 + 7.5 + 7.5 = 81.875; B: 26.25 + 21.875 + 6.25 + 7.5 + 6.25 = 68.125
        $this->assertEqualsWithDelta(81.88, $response->json('data.leader.sheet.totals.A'), 0.01);
        $this->assertEqualsWithDelta(68.13, $response->json('data.leader.sheet.totals.B'), 0.01);
        $this->assertSame(0, $response->json('data.leader.sheet.flag_count'));

        $this->postJson($this->url("sessions/{$session->id}/announce"))
            ->assertOk()
            ->assertJsonPath('data.status', 'announced')
            ->assertJsonPath('data.result.winner', 'A');

        // Any member sees the result.
        $this->actingAsApi($this->students['B'][1][1])->getJson($this->url("sessions/{$session->id}"))
            ->assertOk()
            ->assertJsonPath('data.result.winner', 'A')
            ->assertJsonPath('data.leader', null)
            ->assertJsonPath('data.can_appeal', true);
    }

    public function test_a_judge_never_sees_the_other_judges_levels(): void
    {
        $session = $this->startS1();
        $this->judgeScores($this->students['A'][2][0], $session, [
            'intro' => [4, 1], 'plant_3d' => [3, 3], 'compliance' => [4, 2], 'change_order' => [3, 3], 'defence' => [3, 2],
        ]);

        $json = $this->actingAsApi($this->students['B'][2][0])->getJson($this->url("sessions/{$session->id}"))
            ->assertOk()
            ->assertJsonPath('data.leader', null)
            ->assertJsonPath('data.my_judge.slot', 2)
            ->assertJsonPath('data.my_judge.scores', [])
            ->json();

        $this->assertStringNotContainsString('Seen on slide', json_encode($json));
    }

    public function test_judges_come_from_the_group_that_is_not_presenting_and_never_a_consultant(): void
    {
        $session = $this->s1();
        $this->actingAsApi($this->leader)->patchJson($this->url("sessions/{$session->id}"), [
            'first_company' => 'A', 'lead_group_a' => 1, 'lead_group_b' => 2,
        ])->assertOk();

        // A presenting member of Company A cannot judge.
        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 1, 'user_id' => $this->students['A'][1][1]->id])
            ->assertStatus(422);
        // Slot 2 belongs to Company B.
        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 2, 'user_id' => $this->students['A'][2][1]->id])
            ->assertStatus(422);

        $consultant = $this->students['B'][1][2];
        $this->game->members()->where('user_id', $consultant->id)->update(['status' => 'consultant']);
        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 2, 'user_id' => $consultant->id])
            ->assertStatus(422);

        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 2, 'user_id' => $this->students['B'][1][1]->id])
            ->assertOk();
    }

    public function test_every_row_needs_evidence_before_the_card_is_submitted(): void
    {
        $session = $this->startS1();
        $judge = $this->students['A'][2][0];

        $this->actingAsApi($judge)->postJson($this->url("sessions/{$session->id}/declare"), ['conflict' => false])->assertOk();
        $this->putJson($this->url("sessions/{$session->id}/card"), ['scores' => [
            ['criterion' => 'intro', 'level_a' => 3, 'level_b' => 3, 'evidence' => 'Scope clear'],
            ['criterion' => 'plant_3d', 'level_a' => 3, 'level_b' => 3, 'evidence' => ''],
            ['criterion' => 'compliance', 'level_a' => 0, 'level_b' => 3, 'evidence' => 'A had no slide'],
            ['criterion' => 'change_order', 'level_a' => 3, 'level_b' => 3, 'evidence' => 'Adapted'],
            ['criterion' => 'defence', 'level_a' => 3, 'level_b' => 3, 'evidence' => 'All spoke'],
        ]])->assertOk();

        $this->postJson($this->url("sessions/{$session->id}/card/submit"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Write a line of evidence for 3D Plant Model.');

        // Level 0 is only for the compliance slide.
        $this->putJson($this->url("sessions/{$session->id}/card"), ['scores' => [
            ['criterion' => 'intro', 'level_a' => 0, 'level_b' => 3, 'evidence' => 'x'],
        ]])->assertStatus(422);

        // A criterion outside this session is refused.
        $this->putJson($this->url("sessions/{$session->id}/card"), ['scores' => [
            ['criterion' => 'software', 'level_a' => 3, 'level_b' => 3, 'evidence' => 'x'],
        ]])->assertStatus(422);
    }

    public function test_flags_open_one_reconcile_round_where_judges_change_only_their_own_levels(): void
    {
        $session = $this->startS1();
        $j1 = $this->students['A'][2][0];
        $j2 = $this->students['B'][2][0];
        $this->judgeScores($j1, $session, [
            'intro' => [4, 3], 'plant_3d' => [3, 3], 'compliance' => [3, 3], 'change_order' => [3, 3], 'defence' => [3, 3],
        ]);
        // Judge 2 is one level higher on 3D Plant, so the totals stay within 10 marks
        // and only the Introduction row is flagged.
        $this->judgeScores($j2, $session, [
            'intro' => [2, 3], 'plant_3d' => [4, 3], 'compliance' => [3, 3], 'change_order' => [3, 3], 'defence' => [3, 3],
        ]);

        $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.leader.sheet.criteria.0.a.flag', true)
            ->assertJsonPath('data.leader.reconcilable', ['intro'])
            ->assertJsonPath('data.leader.can_reconcile', true);

        $this->postJson($this->url("sessions/{$session->id}/reconcile"))->assertOk()->assertJsonPath('data.status', 'reconcile');

        $this->actingAsApi($j2)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.my_judge.reconcile_criteria', ['intro']);

        // Judge 2 moves Intro A to 3; a change to an unflagged row is ignored.
        $this->putJson($this->url("sessions/{$session->id}/card"), ['scores' => [
            ['criterion' => 'intro', 'level_a' => 3, 'level_b' => 3],
            ['criterion' => 'defence', 'level_a' => 1, 'level_b' => 1],
        ]])->assertOk();
        $this->postJson($this->url("sessions/{$session->id}/card/reconciled"))->assertOk();
        $this->actingAsApi($j1)->postJson($this->url("sessions/{$session->id}/card/reconciled"))->assertOk();

        $response = $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.status', 'compiled')
            ->assertJsonPath('data.leader.sheet.criteria.0.a.j2', 3)
            ->assertJsonPath('data.leader.sheet.criteria.0.a.flag', false)
            ->assertJsonPath('data.leader.can_reconcile', false);

        $this->assertSame(3, $response->json('data.leader.sheet.criteria.4.a.j2'));

        $score = $session->judges()->where('slot', 2)->first()->scores()->where('criterion', 'intro')->first();
        $this->assertSame(2, $score->level_a);
        $this->assertSame(3, $score->level_a_after);

        $this->postJson($this->url("sessions/{$session->id}/reconcile"))->assertStatus(422);
    }

    public function test_level_marks_go_to_the_tie_break_and_then_sudden_death(): void
    {
        $session = $this->startS1();
        $same = ['intro' => [3, 3], 'plant_3d' => [3, 3], 'compliance' => [3, 3], 'change_order' => [3, 3], 'defence' => [3, 3]];
        $this->judgeScores($this->students['A'][2][0], $session, $same);
        $this->judgeScores($this->students['B'][2][0], $session, $same);

        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))
            ->assertStatus(422);
        $this->postJson($this->url("sessions/{$session->id}/announce"), ['sudden_death_winner' => 'B'])
            ->assertOk()
            ->assertJsonPath('data.result.winner', 'B')
            ->assertJsonPath('data.result.tie_break', 'sudden_death');
    }

    public function test_the_compliance_slide_breaks_a_tie_first(): void
    {
        $session = $this->startS1();
        // Equal totals: A is one level up on compliance (+2.5) and one down on change order (−2.5).
        $this->judgeScores($this->students['A'][2][0], $session, [
            'intro' => [3, 3], 'plant_3d' => [3, 3], 'compliance' => [4, 3], 'change_order' => [2, 3], 'defence' => [3, 3],
        ]);
        $this->judgeScores($this->students['B'][2][0], $session, [
            'intro' => [3, 3], 'plant_3d' => [3, 3], 'compliance' => [3, 3], 'change_order' => [3, 3], 'defence' => [3, 3],
        ]);

        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))
            ->assertOk()
            ->assertJsonPath('data.result.winner', 'A')
            ->assertJsonPath('data.result.tie_break', 'compliance');
    }

    public function test_the_losing_md_retrenches_privately_within_the_floors(): void
    {
        $session = $this->startS1();
        $low = ['intro' => [2, 4], 'plant_3d' => [2, 4], 'compliance' => [2, 4], 'change_order' => [2, 4], 'defence' => [2, 4]];
        $this->judgeScores($this->students['A'][2][0], $session, $low);
        $this->judgeScores($this->students['B'][2][0], $session, $low);
        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))->assertJsonPath('data.result.winner', 'B');

        $md = $this->students['A'][1][0];
        $target = $this->students['A'][2][1];

        $this->actingAsApi($md)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.retrenchment_task.company', 'A')
            ->assertJsonCount(5, 'data.retrenchment_task.eligible');

        // Only the losing MD names the student.
        $this->actingAsApi($this->students['A'][1][1])
            ->postJson($this->url("sessions/{$session->id}/retrench"), ['user_id' => $target->id, 'reason' => 'Volunteered'])
            ->assertStatus(422);

        $this->actingAsApi($md)
            ->postJson($this->url("sessions/{$session->id}/retrench"), ['user_id' => $target->id, 'reason' => 'Volunteered'])
            ->assertOk();

        $this->assertSame('consultant', $this->game->members()->where('user_id', $target->id)->value('status'));

        // The Leader learns a name was given, not whose.
        $json = $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.leader.retrenchment', ['company' => 'A', 'status' => 'named'])
            ->assertJsonPath('data.leader.company_sizes.A', 5)
            ->json('data.leader');
        $this->assertStringNotContainsString('Volunteered', json_encode($json));
    }

    public function test_a_group_never_goes_below_two_or_a_company_below_four(): void
    {
        foreach ([$this->students['A'][2][1], $this->students['A'][2][2]] as $i => $user) {
            // Group 2 already at 2 after one retrenchment; one more from group 1 leaves the company at 4.
            if ($i === 0) {
                $this->game->members()->where('user_id', $user->id)->update(['status' => 'consultant']);
            }
        }

        $session = $this->startS1();
        $low = ['intro' => [2, 4], 'plant_3d' => [2, 4], 'compliance' => [2, 4], 'change_order' => [2, 4], 'defence' => [2, 4]];
        $this->judgeScores($this->students['A'][2][0], $session, $low);
        $this->judgeScores($this->students['B'][2][0], $session, $low);
        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))->assertOk();

        $eligible = $this->actingAsApi($this->students['A'][1][0])->getJson($this->url("sessions/{$session->id}"))
            ->json('data.retrenchment_task.eligible');

        // Group 2 is at 2 and the MD stays, so only group 1's other two members can go.
        $this->assertEqualsCanonicalizing(
            [$this->students['A'][1][1]->id, $this->students['A'][1][2]->id],
            array_column($eligible, 'user_id'),
        );
    }

    public function test_a_company_at_four_retrenches_nobody(): void
    {
        $this->game->members()->whereIn('user_id', [$this->students['A'][1][1]->id, $this->students['A'][2][1]->id])
            ->update(['status' => 'consultant']);

        $session = $this->startS1();
        $low = ['intro' => [2, 4], 'plant_3d' => [2, 4], 'compliance' => [2, 4], 'change_order' => [2, 4], 'defence' => [2, 4]];
        $this->judgeScores($this->students['A'][2][0], $session, $low);
        $this->judgeScores($this->students['B'][2][0], $session, $low);

        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))
            ->assertJsonPath('data.leader.retrenchment.status', 'at_floor');
    }

    public function test_hiring_follows_the_winner_first_and_hire_back_caps_at_six(): void
    {
        $consultantA = $this->students['A'][1][2];
        $consultantB = $this->students['B'][1][2];
        $this->game->members()->whereIn('user_id', [$consultantA->id, $consultantB->id])->update(['status' => 'consultant']);

        $session = $this->startS1();
        $high = ['intro' => [4, 2], 'plant_3d' => [4, 2], 'compliance' => [4, 2], 'change_order' => [4, 2], 'defence' => [4, 2]];
        $this->judgeScores($this->students['A'][2][0], $session, $high);
        $this->judgeScores($this->students['B'][2][0], $session, $high);
        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))->assertJsonPath('data.result.winner', 'A');

        $hires = $this->url("sessions/{$session->id}/hires");
        $this->postJson($hires, ['company' => 'B', 'kind' => 'hire', 'consultant_id' => $consultantB->id])
            ->assertStatus(422)->assertJsonPath('message', 'The winner chooses first.');
        $this->postJson($hires, ['company' => 'A', 'kind' => 'hire', 'consultant_id' => $consultantB->id])->assertOk();
        $this->postJson($hires, ['company' => 'B', 'kind' => 'hire', 'consultant_id' => $consultantB->id])
            ->assertStatus(422)->assertJsonPath('message', 'A consultant works for one company per session.');
        $this->postJson($hires, ['company' => 'B', 'kind' => 'hire', 'consultant_id' => $consultantA->id])->assertOk();

        $this->postJson($hires, ['company' => 'A', 'kind' => 'hire_back', 'consultant_id' => $consultantA->id])
            ->assertOk()
            ->assertJsonPath('data.leader.company_sizes.A', 6);
        $this->assertSame('active', $this->game->members()->where('user_id', $consultantA->id)->value('status'));
    }

    public function test_members_appeal_once_before_noon_the_next_day(): void
    {
        $session = $this->startS1();
        $same = ['intro' => [4, 3], 'plant_3d' => [3, 3], 'compliance' => [3, 3], 'change_order' => [3, 3], 'defence' => [3, 3]];
        $this->judgeScores($this->students['A'][2][0], $session, $same);
        $this->judgeScores($this->students['B'][2][0], $session, $same);
        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))->assertOk();

        $student = $this->students['B'][1][1];
        $body = 'Our compliance slide cited B31.3 para 304.1 and meets level 4.';
        $this->actingAsApi($student)->postJson($this->url("sessions/{$session->id}/appeal"), ['body' => $body])->assertOk()
            ->assertJsonPath('data.my_appeal.body', $body)
            ->assertJsonPath('data.can_appeal', false);
        $this->postJson($this->url("sessions/{$session->id}/appeal"), ['body' => $body])->assertStatus(422);

        $this->travelTo($session->fresh()->appealDeadline()->addMinute());
        $this->actingAsApi($this->students['B'][1][2])->postJson($this->url("sessions/{$session->id}/appeal"), ['body' => $body])
            ->assertStatus(422)->assertJsonPath('message', 'Appeals closed at noon the day after the session.');
    }

    public function test_only_the_leader_runs_the_session_and_outsiders_get_404(): void
    {
        $session = $this->s1();

        $this->actingAsApi($this->students['A'][1][1])
            ->postJson($this->url("sessions/{$session->id}/start"))
            ->assertStatus(403);

        $outsider = $this->createMember($this->tenant);
        $this->actingAsApi($outsider)->getJson($this->url("sessions/{$session->id}"))->assertNotFound();
        $this->getJson($this->url("games/{$this->game->id}"))->assertNotFound();

        $otherTenant = $this->createTenant();
        $stranger = $this->createMember($otherTenant);
        $this->actingAsApi($stranger)
            ->getJson($this->tenantApi($otherTenant, "live/tender/sessions/{$session->id}"))
            ->assertNotFound();
    }

    public function test_a_conflict_frees_the_slot_for_a_substitute(): void
    {
        $session = $this->startS1();
        $first = $this->students['A'][2][0];

        $this->actingAsApi($first)->postJson($this->url("sessions/{$session->id}/declare"), ['conflict' => true])->assertOk()
            ->assertJsonPath('data.my_judge', null);

        $this->actingAsApi($this->leader)
            ->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 1, 'user_id' => $this->students['A'][2][1]->id])
            ->assertOk()
            ->assertJsonPath('data.leader.judges.0.name', $this->students['A'][2][1]->name);
    }

    public function test_the_game_lists_my_tasks_and_standings(): void
    {
        $session = $this->startS1();

        $this->actingAsApi($this->students['A'][2][0])->getJson($this->url(''))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Project TERATAI')
            ->assertJsonPath('data.0.me.company', 'A');

        $this->getJson($this->url("games/{$this->game->id}"))
            ->assertOk()
            ->assertJsonPath('data.sessions.0.code', 'S1')
            ->assertJsonPath('data.sessions.0.my_task', 'judge')
            ->assertJsonCount(7, 'data.sessions')
            ->assertJsonPath('data.companies.0.size', 6);
    }
}
