<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\TenderGame;
use App\Models\TenderSession;
use App\Models\User;
use App\Services\Tender\TenderFlow;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The lecturer's tender pages, the projector and the Grand Final guest link.
 * Extends the API test case for its tenant fixtures.
 */
class TenderGameWebTest extends ApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private Course $course;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'BTG3333']);
        $this->section = $this->createSection($this->course, [], [$this->lecturer]);
    }

    private function base(string $path = ''): string
    {
        return "/{$this->tenant->slug}/courses/{$this->course->id}/tender".$path;
    }

    private function game(): TenderGame
    {
        $this->actingAs($this->lecturer)->post($this->base(), [
            'title' => 'Project TERATAI',
            'section_id' => $this->section->id,
            'company_a_name' => 'Alpha Piping',
            'company_b_name' => 'Beta Engineering',
        ])->assertRedirect();

        return TenderGame::withoutGlobalScopes()->latest('id')->first();
    }

    /** @return array{leader: User, a: list<User>, b: list<User>} */
    private function roster(TenderGame $game): array
    {
        $people = ['leader' => $this->createMember($this->tenant), 'a' => [], 'b' => []];
        $this->enroll($this->section, $people['leader']);
        $members = [$people['leader']->id => ['role' => 'leader']];

        foreach (['A', 'B'] as $company) {
            for ($i = 0; $i < 6; $i++) {
                $user = $this->createMember($this->tenant);
                $this->enroll($this->section, $user);
                $people[strtolower($company)][] = $user;
                $members[$user->id] = [
                    'role' => 'student',
                    'company' => $company,
                    'group_no' => $i < 3 ? 1 : 2,
                    'is_md' => $i === 0 ? 1 : 0,
                    'status' => 'active',
                ];
            }
        }

        $this->actingAs($this->lecturer)
            ->put($this->base("/{$game->id}/roster"), ['members' => $members])
            ->assertRedirect()
            ->assertSessionHas('success');

        return $people;
    }

    private function scoreAndAnnounce(TenderSession $session, array $people): void
    {
        $flow = app(TenderFlow::class);
        app()->instance('current_tenant', $this->tenant);
        $flow->setup($session, ['first_company' => 'A', 'lead_group_a' => 1, 'lead_group_b' => 1]);
        $j1 = $flow->assignJudge($session, 1, $people['a'][3]);
        $j2 = $flow->assignJudge($session, 2, $people['b'][3]);
        $flow->start($session->fresh());

        foreach ([$j1, $j2] as $judge) {
            $judge = $judge->fresh();
            $flow->declare($judge, false);
            $rows = [];
            foreach (array_keys($session->criteria()) as $key) {
                $rows[] = ['criterion' => $key, 'level_a' => 4, 'level_b' => 2, 'evidence' => 'Evidence for '.$key];
            }
            $flow->saveCard($judge->fresh(), $rows);
            $flow->submit($judge->fresh());
        }

        $flow->announce($session->fresh());
        app()->forgetInstance('current_tenant');
    }

    public function test_creating_a_game_makes_its_seven_sessions_and_the_roster_saves(): void
    {
        $game = $this->game();

        $this->assertSame(['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'GF'], $game->sessions()->pluck('code')->all());
        $this->assertSame(5, $game->sessions()->where('code', 'S1')->value('week'));

        $people = $this->roster($game);
        $this->assertSame(13, $game->members()->count());
        $this->assertTrue($game->members()->where('user_id', $people['a'][0]->id)->value('is_md'));

        $this->actingAs($this->lecturer)->get($this->base("/{$game->id}"))
            ->assertOk()
            ->assertSee('Alpha Piping')
            ->assertSee('Session 1')
            ->assertSee('Grand Final');
    }

    public function test_two_mds_in_one_company_are_refused(): void
    {
        $game = $this->game();
        $a = $this->createMember($this->tenant);
        $b = $this->createMember($this->tenant);
        $this->enroll($this->section, $a);
        $this->enroll($this->section, $b);

        $this->actingAs($this->lecturer)->put($this->base("/{$game->id}/roster"), ['members' => [
            $a->id => ['role' => 'student', 'company' => 'A', 'group_no' => 1, 'is_md' => 1],
            $b->id => ['role' => 'student', 'company' => 'A', 'group_no' => 2, 'is_md' => 1],
        ]])->assertSessionHas('error');

        $this->assertSame(0, $game->members()->count());
    }

    public function test_the_session_page_shows_cards_sheet_and_approve_records_the_review(): void
    {
        $game = $this->game();
        $people = $this->roster($game);
        $session = $game->sessions()->where('code', 'S1')->first();
        $this->scoreAndAnnounce($session, $people);

        $this->actingAs($this->lecturer)->get($this->base("/{$game->id}/sessions/{$session->id}"))
            ->assertOk()
            ->assertSee('Evidence for intro')
            ->assertSee('Compile sheet')
            ->assertSee('Approve');

        $this->post($this->base("/{$game->id}/sessions/{$session->id}/review"), ['decision' => 'approved'])
            ->assertSessionHas('success');
        $this->assertSame('approved', $session->fresh()->review_status);

        $csv = $this->get($this->base("/{$game->id}/export"))->assertOk()->streamedContent();
        $this->assertStringContainsString('Introduction and Scope', $csv);
        $this->assertStringContainsString('Company (every member)', $csv);
        $this->assertStringContainsString('Alpha Piping', $csv);
    }

    public function test_a_query_needs_a_comment_and_unlocking_a_card_reopens_scoring(): void
    {
        $game = $this->game();
        $people = $this->roster($game);
        $session = $game->sessions()->where('code', 'S1')->first();
        $this->scoreAndAnnounce($session, $people);

        $this->actingAs($this->lecturer)
            ->post($this->base("/{$game->id}/sessions/{$session->id}/review"), ['decision' => 'queried'])
            ->assertSessionHas('error');

        $this->post($this->base("/{$game->id}/sessions/{$session->id}/review"), ['decision' => 'queried', 'comment' => 'Check B on intro'])
            ->assertSessionHas('success');

        $judge = $session->activeJudges()->where('slot', 2)->first();
        $this->post($this->base("/{$game->id}/sessions/{$session->id}/judges/{$judge->id}/unlock"))->assertSessionHas('success');

        $this->assertSame('scoring', $session->fresh()->status);
        $this->assertNull($judge->fresh()->submitted_at);
    }

    public function test_the_grand_final_guest_link_scores_without_an_account(): void
    {
        $game = $this->game();
        $this->roster($game);
        $final = $game->sessions()->where('code', 'GF')->first();

        $this->actingAs($this->lecturer)
            ->post($this->base("/{$game->id}/sessions/{$final->id}/staff-judges"), ['slot' => 1, 'mode' => 'me'])
            ->assertSessionHas('success');
        $link = $this->post($this->base("/{$game->id}/sessions/{$final->id}/staff-judges"), [
            'slot' => 2, 'mode' => 'guest', 'guest_name' => 'Dr Visiting',
        ])->assertSessionHas('guest_link')->getSession()->get('guest_link');

        // Students cannot be Grand Final Judges.
        app()->instance('current_tenant', $this->tenant);
        app(TenderFlow::class)->start($final->fresh());
        app()->forgetInstance('current_tenant');

        $scores = [];
        foreach (array_keys($final->criteria()) as $key) {
            $scores[$key] = ['level_a' => 3, 'level_b' => 4, 'evidence' => 'Guest saw '.$key];
        }

        auth()->logout();
        $path = parse_url($link, PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('Dr Visiting');
        $this->post($path, ['declare' => 1, 'scores' => $scores, 'submit' => 1])->assertSessionHas('success');

        $guest = $final->activeJudges()->where('slot', 2)->first();
        $this->assertNotNull($guest->submitted_at);
        $this->assertSame('scoring', $final->fresh()->status);

        // The lecturer's own card completes the sheet.
        $this->actingAs($this->lecturer)
            ->post($this->base("/{$game->id}/sessions/{$final->id}/card"), ['declare' => 1, 'scores' => $scores, 'submit' => 1])
            ->assertRedirect($this->base("/{$game->id}/sessions/{$final->id}"));
        $this->assertSame('compiled', $final->fresh()->status);

        $this->get('/tender-judge/not-a-token')->assertNotFound();
    }

    public function test_the_projector_is_for_the_leader_and_lecturer_and_hides_judges(): void
    {
        $game = $this->game();
        $people = $this->roster($game);
        $session = $game->sessions()->where('code', 'S1')->first();
        $this->scoreAndAnnounce($session, $people);

        $state = "/{$this->tenant->slug}/tender/sessions/{$session->id}/projector/state";

        $this->actingAs($people['leader'])->getJson($state)
            ->assertOk()
            ->assertJsonPath('winner', 'Alpha Piping')
            ->assertJsonMissingPath('judges_names');
        $this->assertStringNotContainsString($people['a'][3]->name, $this->getJson($state)->content());

        $this->actingAs($this->lecturer)->get("/{$this->tenant->slug}/tender/sessions/{$session->id}/projector")->assertOk();

        $this->actingAs($people['a'][1])->getJson($state)->assertForbidden();
    }

    public function test_the_lecturer_assigns_judges_from_both_companies_under_the_same_rules(): void
    {
        $game = $this->game();
        $people = $this->roster($game);
        $session = $game->sessions()->where('code', 'S1')->first();
        $session->update(['lead_group_a' => 1, 'lead_group_b' => 2]);
        $url = $this->base("/{$game->id}/sessions/{$session->id}/judges");

        $this->actingAs($this->lecturer)->get($this->base("/{$game->id}/sessions/{$session->id}"))
            ->assertOk()
            ->assertSee($people['a'][3]->name)
            ->assertDontSee('<option value="'.$people['a'][0]->id.'"', false);

        $this->post($url, ['slot' => 1, 'user_id' => $people['a'][3]->id])->assertSessionHas('success');
        $this->post($url, ['slot' => 2, 'user_id' => $people['b'][0]->id])->assertSessionHas('success');
        // Company B's group 2 is presenting, so its members cannot judge.
        $this->post($url, ['slot' => 2, 'user_id' => $people['b'][4]->id])->assertSessionHas('error');

        $this->assertSame(
            [1 => $people['a'][3]->id, 2 => $people['b'][0]->id],
            $session->activeJudges()->pluck('user_id', 'slot')->all(),
        );

        // A second choice replaces the first while the card is open.
        $this->post($url, ['slot' => 1, 'user_id' => $people['a'][4]->id])->assertSessionHas('success');
        $this->assertSame($people['a'][4]->id, $session->activeJudges()->where('slot', 1)->value('user_id'));
    }

    public function test_another_lecturer_cannot_open_the_game(): void
    {
        $game = $this->game();
        $other = $this->createMember($this->tenant, 'lecturer');

        $this->actingAs($other)->get($this->base("/{$game->id}"))->assertForbidden();
    }
}
