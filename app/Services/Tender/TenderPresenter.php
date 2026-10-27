<?php

declare(strict_types=1);

namespace App\Services\Tender;

use App\Models\TenderGame;
use App\Models\TenderJudge;
use App\Models\TenderMember;
use App\Models\TenderSession;
use App\Models\User;

/**
 * What each person may see of a tender. A Judge sees only their own card; the
 * Leader sees who has submitted, and the levels only once both cards are in; the
 * retrenched name is never in the Leader's payload; nobody sees a ranking of
 * individual students.
 */
class TenderPresenter
{
    public function __construct(
        private readonly TenderFlow $flow,
        private readonly TenderCompiler $compiler,
    ) {}

    public function gameSummary(TenderGame $game, TenderMember $me): array
    {
        $standings = $this->flow->standings($game);

        return [
            'id' => $game->id,
            'title' => $game->title,
            'course' => [
                'id' => $game->course->id,
                'code' => $game->course->code,
                'title' => $game->course->title,
            ],
            'me' => $this->me($game, $me),
            'companies' => array_map(fn (string $c) => [
                'key' => $c,
                'name' => $game->companyName($c),
                'size' => $this->flow->companySize($game, $c),
                'wins' => $standings['wins'][$c],
            ], TenderGame::COMPANIES),
            'champion' => $standings['champion'],
        ];
    }

    public function game(TenderGame $game, TenderMember $me): array
    {
        $game->loadMissing('sessions', 'course');

        return $this->gameSummary($game, $me) + [
            'sessions' => $game->sessions->map(fn (TenderSession $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'label' => $s->label(),
                'week' => $s->week,
                'status' => $s->status,
                'winner' => $s->status === 'announced' ? $s->winner : null,
                'totals' => $s->status === 'announced' ? ($s->result['totals'] ?? null) : null,
                'review_status' => $s->review_status,
                'my_task' => $this->task($s, $me),
            ])->values()->all(),
        ];
    }

    public function session(TenderSession $session, TenderMember $me, User $user): array
    {
        $game = $session->game;
        $myJudge = $session->activeJudges()->where('user_id', $user->id)->with('scores', 'pitchScores')->first();
        $announced = $session->status === 'announced';

        $data = [
            'id' => $session->id,
            'game_id' => $game->id,
            'code' => $session->code,
            'label' => $session->label(),
            'week' => $session->week,
            'status' => $session->status,
            'is_grand_final' => $session->isGrandFinal(),
            'first_company' => $session->first_company,
            'change_order_note' => $session->change_order_note,
            'scoring_started_at' => $session->scoring_started_at?->toIso8601String(),
            'reconcile_started_at' => $session->reconcile_started_at?->toIso8601String(),
            'announced_at' => $session->announced_at?->toIso8601String(),
            'review_status' => $session->review_status,
            'server_time' => now()->toIso8601String(),
            'companies' => [
                'A' => $game->company_a_name,
                'B' => $game->company_b_name,
            ],
            'criteria' => $this->criteria($session),
            'consultants' => $this->consultants($game),
            'result' => $announced ? [
                'winner' => $session->winner,
                'tie_break' => $session->tie_break,
                'totals' => $session->result['totals'] ?? null,
                'appeal_deadline' => $session->appealDeadline()?->toIso8601String(),
            ] : null,
            'me' => $this->me($game, $me),
            'my_judge' => $myJudge ? $this->judgeCard($myJudge) : null,
            'leader' => $me->runsSessions() ? $this->leaderView($session) : null,
            'retrenchment_task' => $this->retrenchmentTask($session, $me),
            'my_appeal' => null,
            'can_appeal' => false,
        ];

        if ($announced && $me->role === 'student') {
            $appeal = $session->appeals()->where('user_id', $user->id)->first();
            $data['my_appeal'] = $appeal ? [
                'body' => $appeal->body,
                'decision' => $appeal->decision,
                'decision_note' => $appeal->decision_note,
                'created_at' => $appeal->created_at->toIso8601String(),
            ] : null;
            $data['can_appeal'] = ! $appeal && now()->lessThanOrEqualTo($session->appealDeadline());
        }

        return $data;
    }

    public function judgeCard(TenderJudge $judge): array
    {
        $session = $judge->session;
        $reconcile = $session->status === 'reconcile' && $judge->submitted_at && ! $judge->reconciled_at;

        return [
            'id' => $judge->id,
            'slot' => $judge->slot,
            'company' => $judge->company,
            'declared' => $judge->declared_at !== null,
            'submitted' => $judge->submitted_at !== null,
            'reconciled' => $judge->reconciled_at !== null,
            'from_paper' => $judge->entered_by !== null,
            'scores' => $judge->scores->map(fn ($s) => [
                'criterion' => $s->criterion,
                'level_a' => $s->level_a,
                'level_b' => $s->level_b,
                'level_a_after' => $s->level_a_after,
                'level_b_after' => $s->level_b_after,
                'evidence' => $s->evidence,
            ])->values()->all(),
            'pitches' => $judge->pitchScores->map(fn ($p) => [
                'consultant_id' => $p->consultant_id,
                'relevant' => $p->relevant,
                'credible' => $p->credible,
                'standards' => $p->standards,
                'communication' => $p->communication,
            ])->values()->all(),
            // Which rows to look at again; never the other Judge's levels.
            'reconcile_criteria' => $reconcile ? ($session->reconcile_criteria ?? []) : [],
        ];
    }

    /** The full compile sheet, for the Leader once the cards are in and for the lecturer. */
    public function judgesProgress(TenderSession $session): array
    {
        return $session->activeJudges()->with('user')->get()->map(fn (TenderJudge $j) => [
            'id' => $j->id,
            'slot' => $j->slot,
            'name' => $j->displayName(),
            'company' => $j->company,
            'guest' => $j->isGuest(),
            'declared' => $j->declared_at !== null,
            'submitted' => $j->submitted_at !== null,
            'reconciled' => $j->reconciled_at !== null,
            'from_paper' => $j->entered_by !== null,
        ])->values()->all();
    }

    public function pitchRanking(TenderSession $session): array
    {
        $scores = $session->activeJudges()->whereNotNull('submitted_at')->with('pitchScores.consultant')->get()
            ->flatMap(fn (TenderJudge $j) => $j->pitchScores);

        return $scores->groupBy('consultant_id')
            ->map(fn ($rows) => [
                'user_id' => $rows->first()->consultant_id,
                'name' => $rows->first()->consultant->name,
                'total' => round($rows->avg(fn ($p) => $p->total()), 1),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function leaderView(TenderSession $session): array
    {
        $game = $session->game;
        $compiled = in_array($session->status, ['compiled', 'reconcile', 'announced'], true);
        $sheet = $compiled ? ($session->status === 'announced' && $session->result ? $session->result : $this->compiler->compile($session)) : null;

        $eligible = [];
        if (in_array($session->status, ['setup', 'scoring'], true)) {
            foreach ([1, 2] as $slot) {
                $eligible[(string) $slot] = $this->flow->eligibleJudges($session, $slot)->map(fn ($row) => [
                    'user_id' => $row['member']->user_id,
                    'name' => $row['member']->user->name,
                    'group_no' => $row['member']->group_no,
                    'judged_before' => $row['judged_before'],
                ])->all();
            }
        }

        $retrench = $this->flow->retrenchmentStatus($session);

        return [
            'judges' => $this->judgesProgress($session),
            'eligible_judges' => $eligible ?: null,
            'sheet' => $sheet,
            'reconcilable' => $session->status === 'compiled' && ! $session->reconcile_started_at && $sheet
                ? $this->compiler->reconcilable($sheet) : [],
            'can_reconcile' => $session->status === 'compiled' && ! $session->reconcile_started_at && ($sheet['flag_count'] ?? 0) > 0,
            'pitch_ranking' => $compiled ? $this->pitchRanking($session) : [],
            'retrenchment' => $retrench ? ['company' => $this->flow->loser($session), 'status' => $retrench] : null,
            'hires' => $session->rosterEvents()->with('user')->whereIn('kind', ['hire', 'hire_back', 'pass'])->get()
                ->map(fn ($e) => [
                    'kind' => $e->kind,
                    'company' => $e->company,
                    'user_id' => $e->user_id,
                    'name' => $e->user?->name,
                ])->values()->all(),
            'company_sizes' => [
                'A' => $this->flow->companySize($game, 'A'),
                'B' => $this->flow->companySize($game, 'B'),
            ],
        ];
    }

    private function retrenchmentTask(TenderSession $session, TenderMember $me): ?array
    {
        if (! $me->is_md || $me->company !== $this->flow->loser($session)
            || $this->flow->retrenchmentStatus($session) !== 'pending') {
            return null;
        }

        return [
            'company' => $me->company,
            'eligible' => $this->flow->retrenchable($session)->map(fn (TenderMember $m) => [
                'user_id' => $m->user_id,
                'name' => $m->user->name,
                'group_no' => $m->group_no,
            ])->all(),
        ];
    }

    private function task(TenderSession $session, TenderMember $me): ?string
    {
        $judge = $session->activeJudges()->where('user_id', $me->user_id)->first();
        if ($judge && in_array($session->status, ['setup', 'scoring'], true) && ! $judge->submitted_at) {
            return 'judge';
        }
        if ($judge && $session->status === 'reconcile' && ! $judge->reconciled_at) {
            return 'reconcile';
        }
        if ($me->runsSessions() && in_array($session->status, ['setup', 'scoring', 'compiled', 'reconcile'], true)) {
            return 'run';
        }
        if ($me->is_md && $me->company === $this->flow->loser($session)
            && $this->flow->retrenchmentStatus($session) === 'pending') {
            return 'retrench';
        }

        return null;
    }

    private function me(TenderGame $game, TenderMember $me): array
    {
        return [
            'role' => $me->role,
            'company' => $me->company,
            'company_name' => $game->companyName($me->company),
            'group_no' => $me->group_no,
            'is_md' => $me->is_md,
            'status' => $me->status,
        ];
    }

    private function criteria(TenderSession $session): array
    {
        $rows = [];
        foreach ($session->criteria() as $key => $max) {
            $rows[] = [
                'key' => $key,
                'label' => TenderCriteria::label($key),
                'max' => $max,
                'min_level' => TenderCriteria::minLevel($key),
            ];
        }

        return $rows;
    }

    private function consultants(TenderGame $game): array
    {
        return $game->members()->with('user')->where('status', 'consultant')->get()
            ->map(fn (TenderMember $m) => [
                'user_id' => $m->user_id,
                'name' => $m->user->name,
                'company' => $m->company,
            ])->sortBy('name')->values()->all();
    }
}
