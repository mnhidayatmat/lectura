<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Live;

use App\Models\Tenant;
use App\Models\TenderGame;
use App\Models\TenderSession;
use App\Models\User;
use App\Services\Tender\TenderFlow;
use Tests\Feature\Api\V1\ApiTestCase;

/** The BTG2663 Ophir Offshore Tender ruleset, on the same endpoints as the Piping game. */
class OphirTenderApiTest extends ApiTestCase
{
    private Tenant $tenant;

    private TenderGame $game;

    private User $leader;

    /** @var array<string, list<User>> company => students, the MD first */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $lecturer = $this->createMember($this->tenant, 'lecturer');
        $course = $this->createCourse($this->tenant, $lecturer, ['code' => 'BTG2663']);

        app()->instance('current_tenant', $this->tenant);
        $this->game = app(TenderFlow::class)->createGame([
            'tenant_id' => $this->tenant->id,
            'course_id' => $course->id,
            'title' => 'Ophir',
            'ruleset' => 'ophir',
        ]);
        app()->forgetInstance('current_tenant');

        $this->leader = $this->createMember($this->tenant);
        $this->game->members()->create(['user_id' => $this->leader->id, 'role' => 'leader']);

        // A plays with 11, B with 10: the floor of 9 lets A lose two and B one.
        foreach (['A' => 11, 'B' => 10] as $company => $size) {
            for ($i = 0; $i < $size; $i++) {
                $user = $this->createMember($this->tenant);
                $this->game->members()->create([
                    'user_id' => $user->id,
                    'company' => $company,
                    'group_no' => $i % 2 + 1,
                    'is_md' => $i === 0,
                ]);
                $this->students[$company][] = $user;
            }
        }
    }

    private function url(string $path): string
    {
        return $this->tenantApi($this->tenant, 'live/tender/'.ltrim($path, '/'));
    }

    private function sessionFor(string $code): TenderSession
    {
        return $this->game->sessions()->where('code', $code)->first();
    }

    private function start(string $code = 'S1'): TenderSession
    {
        $session = $this->sessionFor($code);

        $this->actingAsApi($this->leader)
            ->patchJson($this->url("sessions/{$session->id}"), ['first_company' => 'A', 'change_order_note' => 'Know your field'])
            ->assertOk();
        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 1, 'user_id' => $this->students['A'][1]->id])->assertOk();
        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 2, 'user_id' => $this->students['B'][1]->id])->assertOk();
        $this->postJson($this->url("sessions/{$session->id}/start"))->assertOk();

        return $session;
    }

    /** @param array<string, array{0: int, 1: int}> $overrides criterion => [A, B] marks; the rest are equal */
    private function card(User $judge, TenderSession $session, array $overrides = []): void
    {
        $marks = array_merge([
            'technical' => [20, 20], 'compliance' => [20, 20], 'data' => [8, 8], 'integration' => [8, 8],
            'defence' => [8, 8], 'communication' => [8, 8], 'progress' => [8, 8],
        ], $overrides);

        $this->actingAsApi($judge)->postJson($this->url("sessions/{$session->id}/declare"), ['conflict' => false])->assertOk();
        $this->putJson($this->url("sessions/{$session->id}/card"), ['scores' => array_map(
            fn ($key) => ['criterion' => $key, 'level_a' => $marks[$key][0], 'level_b' => $marks[$key][1], 'evidence' => 'Seen'],
            array_keys($marks),
        )])->assertOk();
        $this->postJson($this->url("sessions/{$session->id}/card/submit"))->assertOk();
    }

    public function test_seven_sessions_on_weeks_five_to_eleven_with_the_same_hundred_marks(): void
    {
        $this->assertSame(
            ['S1' => 5, 'S2' => 6, 'S3' => 7, 'S4' => 8, 'S5' => 9, 'S6' => 10, 'GF' => 11],
            $this->game->sessions->pluck('week', 'code')->all(),
        );

        $response = $this->actingAsApi($this->students['A'][1])->getJson($this->url("sessions/{$this->sessionFor('S4')->id}"))
            ->assertOk()
            ->assertJsonPath('data.rules.key', 'ophir')
            ->assertJsonPath('data.rules.scale', 'marks')
            ->assertJsonPath('data.rules.note_label', 'Mission')
            ->assertJsonPath('data.criteria.0.label', 'Technical correctness and depth')
            ->assertJsonPath('data.criteria.0.min_level', 0)
            ->assertJsonPath('data.criteria.0.max_level', 25)
            ->assertJsonPath('data.criteria.0.bands', [
                ['label' => 'Excellent', 'min' => 23, 'max' => 25],
                ['label' => 'Good', 'min' => 18, 'max' => 22],
                ['label' => 'Fair', 'min' => 13, 'max' => 17],
                ['label' => 'Poor', 'min' => 0, 'max' => 12],
            ])
            ->assertJsonPath('data.criteria.2.bands.0', ['label' => 'Excellent', 'min' => 9, 'max' => 10]);

        $this->assertSame(100.0, (float) collect($response->json('data.criteria'))->sum('max'));
    }

    public function test_marks_are_averaged_and_flagged_at_twenty_percent(): void
    {
        $session = $this->start();

        $this->actingAsApi($this->students['A'][1])->postJson($this->url("sessions/{$session->id}/declare"), ['conflict' => false]);
        $this->putJson($this->url("sessions/{$session->id}/card"), ['scores' => [['criterion' => 'data', 'level_a' => 11]]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Data judgement takes a mark from 0 to 10.');

        // Technical 5 apart on 25 is 20%: flagged. Data 1 apart on 10 is not.
        $this->card($this->students['A'][1], $session, ['technical' => [22, 15], 'data' => [9, 6]]);
        $this->card($this->students['B'][1], $session, ['technical' => [17, 15], 'data' => [8, 6]]);

        $sheet = $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.status', 'compiled')
            ->json('data.leader.sheet');

        $technical = collect($sheet['criteria'])->firstWhere('key', 'technical');
        $data = collect($sheet['criteria'])->firstWhere('key', 'data');
        $this->assertEqualsWithDelta(19.5, $technical['a']['mark'], 0.001);
        $this->assertTrue($technical['a']['flag']);
        $this->assertFalse($technical['b']['flag']);
        $this->assertEqualsWithDelta(8.5, $data['a']['mark'], 0.001);
        $this->assertFalse($data['a']['flag']);
        // A: 19.5 + 20 + 8.5 + 8 × 4 = 80; B: 15 + 20 + 6 + 32 = 73.
        $this->assertEqualsWithDelta(80.0, $sheet['totals']['A'], 0.001);
        $this->assertEqualsWithDelta(73.0, $sheet['totals']['B'], 0.001);
        $this->assertSame('A', $sheet['winner']);
    }

    public function test_equal_totals_go_to_compliance_defence_then_technical(): void
    {
        $session = $this->start();
        $equalTotals = ['technical' => [21, 20], 'progress' => [7, 8]];
        $this->card($this->students['A'][1], $session, $equalTotals);
        $this->card($this->students['B'][1], $session, $equalTotals);

        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))
            ->assertOk()
            ->assertJsonPath('data.result.winner', 'A')
            ->assertJsonPath('data.result.tie_break', 'technical');
    }

    public function test_the_loser_retrenches_two_and_never_below_nine(): void
    {
        $session = $this->start();
        $this->card($this->students['A'][1], $session, ['technical' => [10, 20]]);
        $this->card($this->students['B'][1], $session, ['technical' => [10, 20]]);
        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))->assertJsonPath('data.result.winner', 'B');

        $md = $this->students['A'][0];
        $this->actingAsApi($md)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.retrenchment_task.remaining', 2)
            ->assertJsonCount(10, 'data.retrenchment_task.eligible');

        $this->postJson($this->url("sessions/{$session->id}/retrench"), ['user_id' => $this->students['A'][2]->id, 'reason' => 'Volunteered'])->assertOk();

        $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.leader.retrenchment.status', 'pending');
        $this->actingAsApi($md)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.retrenchment_task.remaining', 1);

        $this->postJson($this->url("sessions/{$session->id}/retrench"), ['user_id' => $this->students['A'][3]->id, 'reason' => 'Volunteered'])->assertOk();

        $this->actingAsApi($md)->getJson($this->url("sessions/{$session->id}"))->assertJsonPath('data.retrenchment_task', null);
        $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.leader.retrenchment.status', 'named')
            ->assertJsonPath('data.leader.company_sizes.A', 9);
    }

    public function test_a_company_one_above_the_floor_retrenches_only_one(): void
    {
        $session = $this->start();
        $this->card($this->students['A'][1], $session, ['technical' => [20, 10]]);
        $this->card($this->students['B'][1], $session, ['technical' => [20, 10]]);
        $this->actingAsApi($this->leader)->postJson($this->url("sessions/{$session->id}/announce"))->assertJsonPath('data.result.winner', 'A');

        $this->actingAsApi($this->students['B'][0])
            ->postJson($this->url("sessions/{$session->id}/retrench"), ['user_id' => $this->students['B'][2]->id, 'reason' => 'Volunteered'])
            ->assertOk();

        $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.leader.retrenchment.status', 'named')
            ->assertJsonPath('data.leader.company_sizes.B', 9);
    }

    public function test_four_judges_two_from_each_company_are_averaged(): void
    {
        $session = $this->sessionFor('S1');
        $judges = [1 => $this->students['A'][1], 2 => $this->students['B'][1], 3 => $this->students['A'][2], 4 => $this->students['B'][2]];

        $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.rules.judge_slots', ['1' => 'A', '2' => 'B', '3' => 'A', '4' => 'B'])
            ->assertJsonCount(4, 'data.leader.eligible_judges')
            ->assertJsonCount(11, 'data.leader.eligible_judges.3');

        $this->patchJson($this->url("sessions/{$session->id}"), ['first_company' => 'A'])->assertOk();
        foreach ($judges as $slot => $user) {
            $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => $slot, 'user_id' => $user->id])->assertOk();
        }

        // One slot each, from the slot's own company, and no fifth slot.
        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 3, 'user_id' => $judges[1]->id])->assertStatus(422);
        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 3, 'user_id' => $this->students['B'][3]->id])->assertStatus(422);
        $this->postJson($this->url("sessions/{$session->id}/judges"), ['slot' => 5, 'user_id' => $this->students['A'][3]->id])->assertStatus(422);
        $this->getJson($this->url("sessions/{$session->id}"))->assertJsonCount(10, 'data.leader.eligible_judges.3');

        $this->postJson($this->url("sessions/{$session->id}/start"))->assertOk();

        // Technical for A: 22, 20, 18, 16 averages 19; highest and lowest are 6 apart on 25, flagged.
        foreach ([1 => 22, 2 => 20, 3 => 18, 4 => 16] as $slot => $mark) {
            $this->card($judges[$slot], $session, ['technical' => [$mark, 20]]);
        }

        $sheet = $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$session->id}"))
            ->assertJsonPath('data.status', 'compiled')
            ->json('data.leader.sheet');

        $technical = collect($sheet['criteria'])->firstWhere('key', 'technical');
        $this->assertSame([1 => 22, 2 => 20, 3 => 18, 4 => 16], $technical['a']['judges']);
        $this->assertEqualsWithDelta(19.0, $technical['a']['mark'], 0.001);
        $this->assertTrue($technical['a']['flag']);
        $this->assertCount(4, $sheet['judge_totals']['A']);
        $this->assertFalse($sheet['totals_flag']['A']);
        $this->assertEqualsWithDelta(79.0, $sheet['totals']['A'], 0.001);
        $this->assertEqualsWithDelta(80.0, $sheet['totals']['B'], 0.001);
        $this->assertSame('B', $sheet['winner']);
    }

    public function test_student_judges_score_the_grand_final(): void
    {
        $final = $this->sessionFor('GF');

        $this->actingAsApi($this->leader)->getJson($this->url("sessions/{$final->id}"))
            ->assertJsonCount(11, 'data.leader.eligible_judges.1');

        $this->start('GF');
        $this->assertSame(2, $final->activeJudges()->count());
    }
}
