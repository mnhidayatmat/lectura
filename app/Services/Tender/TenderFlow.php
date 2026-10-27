<?php

declare(strict_types=1);

namespace App\Services\Tender;

use App\Models\TenderAppeal;
use App\Models\TenderGame;
use App\Models\TenderJudge;
use App\Models\TenderMember;
use App\Models\TenderRosterEvent;
use App\Models\TenderSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every state change of a tender session, with the Student Guide's rules
 * (Sections 3, 6 and 7). Controllers decide who is asking; this decides whether
 * the game allows it, and throws TenderRuleException with the reason if not.
 *
 * setup → scoring → compiled ⇄ reconcile → announced; the lecturer's review
 * (approved / queried) sits beside the status, and unlocking a card sends the
 * session back to scoring.
 */
class TenderFlow
{
    public const MIN_COMPANY = 4;

    public const MAX_COMPANY = 6;

    public const MIN_GROUP = 2;

    public function __construct(private readonly TenderCompiler $compiler) {}

    public function createGame(array $attributes): TenderGame
    {
        return DB::transaction(function () use ($attributes) {
            $game = TenderGame::create($attributes);

            foreach (array_keys($game->rules()->defaultWeeks()) as $i => $code) {
                $game->sessions()->create([
                    'code' => $code,
                    'week' => $game->rules()->defaultWeeks()[$code],
                    'sort_order' => $i,
                ]);
            }

            return $game;
        });
    }

    // ── Setup (Leader) ──────────────────────────────────────────────

    public function setup(TenderSession $session, array $data): TenderSession
    {
        $this->expectStatus($session, ['setup'], 'The session has started; its setup is fixed.');

        $session->fill(array_intersect_key($data, array_flip([
            'first_company', 'change_order_note',
        ])))->save();

        return $session;
    }

    /**
     * Student Judges who may take a slot: active members of that slot's company
     * (the ruleset's judgeSlots) not already judging another slot this session.
     * Consultants cannot judge.
     *
     * @return Collection<int, array{member: TenderMember, judged_before: int}>
     */
    public function eligibleJudges(TenderSession $session, int $slot): Collection
    {
        if ($session->staffJudged()) {
            return collect();
        }

        $company = $session->game->rules()->judgeSlots()[$slot] ?? null;
        $elsewhere = $session->activeJudges()->where('slot', '!=', $slot)->whereNotNull('user_id')->pluck('user_id')->all();

        $judgedBefore = TenderJudge::query()
            ->whereNull('replaced_at')
            ->whereNotNull('user_id')
            ->whereHas('session', fn ($q) => $q->where('tender_game_id', $session->tender_game_id)
                ->where('id', '!=', $session->id))
            ->selectRaw('user_id, count(*) as n')
            ->groupBy('user_id')
            ->pluck('n', 'user_id');

        return $session->game->members()->with('user')->get()
            ->filter(fn (TenderMember $m) => $m->isActiveStudent() && $m->company === $company
                && ! in_array($m->user_id, $elsewhere, true))
            ->sortBy(fn (TenderMember $m) => [(int) ($judgedBefore[$m->user_id] ?? 0), $m->user->name])
            ->values()
            ->map(fn (TenderMember $m) => [
                'member' => $m,
                'judged_before' => (int) ($judgedBefore[$m->user_id] ?? 0),
            ]);
    }

    /** Put a student in a Judge slot; an unsubmitted Judge already there is replaced. */
    public function assignJudge(TenderSession $session, int $slot, User $user): TenderJudge
    {
        $this->expectStatus($session, ['setup', 'scoring'], 'Judges can only change before the cards are in.');
        $this->expectSlot($session, $slot);

        if ($session->staffJudged()) {
            throw new TenderRuleException('At the Grand Final the lecturer and an invited staff member score; student Judges do not.');
        }

        $eligible = $this->eligibleJudges($session, $slot)
            ->first(fn ($row) => $row['member']->user_id === $user->id);

        if (! $eligible) {
            throw new TenderRuleException('That student cannot judge this slot: a Judge comes from that slot\'s company, judges one slot only, and consultants cannot judge.');
        }

        return $this->placeJudge($session, $slot, [
            'user_id' => $user->id,
            'company' => $eligible['member']->company,
        ]);
    }

    /** Grand Final: a lecturer of the course scores in a slot. */
    public function assignStaffJudge(TenderSession $session, int $slot, User $user): TenderJudge
    {
        $this->expectGrandFinalStaffChange($session, $slot);

        return $this->placeJudge($session, $slot, ['user_id' => $user->id]);
    }

    /**
     * Grand Final: an invited staff member scores through a one-time link.
     *
     * @return array{0: TenderJudge, 1: string} the Judge and the plain token
     */
    public function assignGuestJudge(TenderSession $session, int $slot, string $name): array
    {
        $this->expectGrandFinalStaffChange($session, $slot);

        $token = Str::random(48);
        $judge = $this->placeJudge($session, $slot, [
            'guest_name' => $name,
            'guest_token_hash' => hash('sha256', $token),
        ]);

        return [$judge, $token];
    }

    public function guestJudge(string $token): ?TenderJudge
    {
        return TenderJudge::where('guest_token_hash', hash('sha256', $token))
            ->whereNull('replaced_at')
            ->first();
    }

    public function start(TenderSession $session): TenderSession
    {
        $this->expectStatus($session, ['setup'], 'The session has already started.');

        if (! $session->staffJudged()) {
            if (! $session->first_company) {
                throw new TenderRuleException('Set who presents first before starting.');
            }
        }

        if (! $session->activeJudges()->exists()) {
            throw new TenderRuleException('Assign the Judges before starting.');
        }

        $session->update(['status' => 'scoring', 'scoring_started_at' => now()]);

        return $session;
    }

    // ── The Judge's card ────────────────────────────────────────────

    /** The declaration, or a conflict of interest: the Leader then names a substitute. */
    public function declare(TenderJudge $judge, bool $conflict): TenderJudge
    {
        $this->expectActiveJudge($judge);
        $this->expectStatus($judge->session, ['setup', 'scoring'], 'Scoring is over.');

        if ($judge->submitted_at) {
            throw new TenderRuleException('Your card is already in.');
        }

        $judge->update($conflict
            ? ['conflict_at' => now(), 'replaced_at' => now()]
            : ['declared_at' => now()]);

        return $judge;
    }

    /**
     * Save levels and evidence. While scoring, any row of an unsubmitted card;
     * in the reconcile round, only the after-reconcile levels of the rows the
     * Judges are asked to look at again.
     *
     * @param  list<array{criterion: string, level_a?: ?int, level_b?: ?int, evidence?: ?string}>  $scores
     * @param  list<array{consultant_id: int, relevant: int, credible: int, standards: int, communication: int}>  $pitches
     */
    public function saveCard(TenderJudge $judge, array $scores, array $pitches = []): TenderJudge
    {
        $this->expectActiveJudge($judge);
        $session = $judge->session;
        $criteria = $session->criteria();

        if ($session->status === 'reconcile') {
            if (! $judge->submitted_at || $judge->reconciled_at) {
                throw new TenderRuleException('You have finished reconciling.');
            }
            $open = $session->reconcile_criteria ?? [];

            DB::transaction(function () use ($judge, $session, $scores, $open, $criteria) {
                foreach ($scores as $row) {
                    $key = $row['criterion'];
                    if (! isset($criteria[$key]) || ! in_array($key, $open, true)) {
                        continue;
                    }
                    $score = $judge->scores()->firstWhere('criterion', $key);
                    if (! $score) {
                        continue;
                    }
                    $score->update([
                        'level_a_after' => $this->changedLevel($session, $key, $row['level_a'] ?? null, $score->level_a),
                        'level_b_after' => $this->changedLevel($session, $key, $row['level_b'] ?? null, $score->level_b),
                    ]);
                }
            });

            return $judge->load('scores');
        }

        $this->expectStatus($session, ['scoring'], 'The card is closed.');
        if ($judge->submitted_at) {
            throw new TenderRuleException('Your card is already in and cannot be changed.');
        }
        if (! $judge->declared_at) {
            throw new TenderRuleException('Confirm the declaration before scoring.');
        }

        DB::transaction(function () use ($judge, $session, $scores, $pitches, $criteria) {
            foreach ($scores as $row) {
                $key = $row['criterion'];
                if (! isset($criteria[$key])) {
                    throw new TenderRuleException($session->game->rules()->label($key).' is not scored this session.');
                }
                $judge->scores()->updateOrCreate(['criterion' => $key], [
                    'level_a' => $this->checkedLevel($session, $key, $row['level_a'] ?? null),
                    'level_b' => $this->checkedLevel($session, $key, $row['level_b'] ?? null),
                    'evidence' => isset($row['evidence']) ? Str::limit(trim((string) $row['evidence']), 500, '') : null,
                ]);
            }

            $this->savePitches($judge, $pitches);
        });

        return $judge->load('scores', 'pitchScores');
    }

    /** Lock the card. Every row needs both levels and a line of evidence. */
    public function submit(TenderJudge $judge): TenderJudge
    {
        $this->expectActiveJudge($judge);
        $this->expectStatus($judge->session, ['scoring'], 'The card is closed.');

        if ($judge->submitted_at) {
            return $judge;
        }

        $scores = $judge->scores()->get()->keyBy('criterion');
        $rules = $judge->session->game->rules();
        foreach (array_keys($judge->session->criteria()) as $key) {
            $score = $scores[$key] ?? null;
            if (! $score || $score->level_a === null || $score->level_b === null) {
                throw new TenderRuleException('Give both companies a '.($rules->isOphir() ? 'mark' : 'level').' for '.$rules->label($key).'.');
            }
            if (blank($score->evidence)) {
                throw new TenderRuleException('Write a line of evidence for '.$rules->label($key).'.');
            }
        }

        $judge->update(['submitted_at' => now()]);
        $this->compileWhenComplete($judge->session->fresh());

        return $judge;
    }

    /**
     * A card the Leader copies in from paper when the phones could not be used.
     */
    public function enterPaperCard(TenderJudge $judge, User $leader, array $scores, array $pitches, ?string $photoPath): TenderJudge
    {
        $this->expectActiveJudge($judge);
        $this->expectStatus($judge->session, ['scoring'], 'Paper cards are entered while the session is scoring.');

        if ($judge->submitted_at) {
            throw new TenderRuleException('That Judge\'s card is already in.');
        }

        $judge->update(['declared_at' => $judge->declared_at ?? now(), 'entered_by' => $leader->id, 'paper_photo_path' => $photoPath]);
        $this->saveCard($judge, $scores, $pitches);

        return $this->submit($judge->fresh());
    }

    // ── Compile and reconcile (Leader) ──────────────────────────────

    public function sheet(TenderSession $session): array
    {
        return $this->compiler->compile($session);
    }

    public function startReconcile(TenderSession $session): TenderSession
    {
        $this->expectStatus($session, ['compiled'], 'Reconcile follows the compiled sheet.');

        if ($session->reconcile_started_at) {
            throw new TenderRuleException('The Judges have already had their reconcile round.');
        }
        $sheet = $this->compiler->compile($session);
        if ($sheet['flag_count'] === 0) {
            throw new TenderRuleException('Nothing is flagged, so there is nothing to reconcile.');
        }

        $session->update([
            'status' => 'reconcile',
            'reconcile_started_at' => now(),
            'reconcile_criteria' => $this->compiler->reconcilable($sheet),
        ]);

        return $session;
    }

    public function finishReconcileFor(TenderJudge $judge): TenderJudge
    {
        $this->expectActiveJudge($judge);
        $this->expectStatus($judge->session, ['reconcile'], 'There is no reconcile round open.');

        $judge->update(['reconciled_at' => now()]);

        $session = $judge->session->fresh();
        if ($session->activeJudges()->whereNotNull('submitted_at')->whereNull('reconciled_at')->doesntExist()) {
            $this->endReconcile($session);
        }

        return $judge;
    }

    public function endReconcile(TenderSession $session): TenderSession
    {
        $this->expectStatus($session, ['reconcile'], 'There is no reconcile round open.');
        $session->update(['status' => 'compiled', 'reconciled_at' => now()]);

        return $session;
    }

    // ── Announcement ────────────────────────────────────────────────

    public function announce(TenderSession $session, ?string $suddenDeathWinner = null): TenderSession
    {
        $this->expectStatus($session, ['compiled'], 'Announce once the sheet is compiled.');

        $sheet = $this->compiler->compile($session);
        $winner = $sheet['winner'];
        $tieBreak = $sheet['tie_break'];

        if ($winner === null) {
            if (! in_array($suddenDeathWinner, TenderGame::COMPANIES, true)) {
                throw new TenderRuleException('The marks and every tie-break are level: record the winner of the sudden-death question.');
            }
            $winner = $suddenDeathWinner;
            $tieBreak = 'sudden_death';
        }

        DB::transaction(function () use ($session, $sheet, $winner, $tieBreak, $suddenDeathWinner) {
            $session->update([
                'status' => 'announced',
                'announced_at' => now(),
                'winner' => $winner,
                'tie_break' => $tieBreak,
                'sudden_death_winner' => $tieBreak === 'sudden_death' ? $suddenDeathWinner : null,
                'result' => $sheet,
                'review_status' => null,
                'review_comment' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            $loser = $winner === 'A' ? 'B' : 'A';
            if ($session->retrenches()
                && $session->rosterEvents()->whereIn('kind', ['retrench', 'at_floor'])->doesntExist()
                && $this->companySize($session->game, $loser) <= $session->game->rules()->minCompany()) {
                $session->rosterEvents()->create(['kind' => 'at_floor', 'company' => $loser]);
            }
        });

        return $session->fresh();
    }

    public function loser(TenderSession $session): ?string
    {
        return match ($session->winner) {
            'A' => 'B',
            'B' => 'A',
            default => null,
        };
    }

    // ── Retrenchment and consultants ────────────────────────────────

    /**
     * Students the losing company's MD may retrench: no group and no company below
     * the ruleset's floor afterwards. The MD stays to run the company.
     *
     * @return Collection<int, TenderMember>
     */
    public function retrenchable(TenderSession $session): Collection
    {
        $company = $this->loser($session);
        if (! $company || ! $session->retrenches()) {
            return collect();
        }

        $active = $session->game->members()->with('user')->get()
            ->filter(fn (TenderMember $m) => $m->isActiveStudent() && $m->company === $company);

        $rules = $session->game->rules();
        if ($active->count() <= $rules->minCompany()) {
            return collect();
        }

        $groupSizes = $active->countBy('group_no');

        return $active
            ->filter(fn (TenderMember $m) => ! $m->is_md && ($groupSizes[$m->group_no] ?? 0) > $rules->minGroup())
            ->sortBy(fn (TenderMember $m) => $m->user->name)
            ->values();
    }

    public function retrenchmentStatus(TenderSession $session): ?string
    {
        if ($session->status !== 'announced' || ! $session->retrenches()) {
            return null;
        }

        $events = $session->rosterEvents()->whereIn('kind', ['retrench', 'at_floor'])->get();
        if ($events->contains('kind', 'at_floor')) {
            return 'at_floor';
        }
        if ($events->isEmpty()) {
            return 'pending';
        }

        // Ophir retrenches two; the MD names them one at a time, and stops early at the floor.
        return $this->retrenchesLeft($session, $events->count()) > 0 ? 'pending' : 'named';
    }

    /** How many more students the losing MD still has to name this session. */
    public function retrenchesLeft(TenderSession $session, ?int $named = null): int
    {
        $named ??= $session->rosterEvents()->where('kind', 'retrench')->count();
        $left = $session->game->rules()->retrenchCount() - $named;

        return $left > 0 && $this->retrenchable($session)->isNotEmpty() ? $left : 0;
    }

    public function retrench(TenderSession $session, TenderMember $md, User $student, string $reason): TenderRosterEvent
    {
        $company = $this->loser($session);
        if ($session->status !== 'announced' || ! $session->retrenches() || ! $company) {
            throw new TenderRuleException('There is no retrenchment after this session.');
        }
        if (! $md->is_md || $md->company !== $company) {
            throw new TenderRuleException('Only the losing company\'s Managing Director names the retrenched student.');
        }
        if ($this->retrenchmentStatus($session) !== 'pending') {
            throw new TenderRuleException('This session\'s retrenchment is already settled.');
        }

        $member = $this->retrenchable($session)->firstWhere('user_id', $student->id);
        if (! $member) {
            throw new TenderRuleException($session->game->rules()->retrenchRule());
        }

        return DB::transaction(function () use ($session, $member, $company, $reason, $md) {
            $member->update(['status' => 'consultant']);

            return $session->rosterEvents()->create([
                'kind' => 'retrench',
                'company' => $company,
                'user_id' => $member->user_id,
                'reason' => $reason,
                'created_by' => $md->user_id,
            ]);
        });
    }

    /**
     * Commissions after the announcement: the winner chooses first (a hire or a
     * pass), then the loser; one commission per company per session and one
     * company per consultant. A hire-back returns one of the winner's own
     * consultants to their group, never above 6.
     */
    public function recordHire(TenderSession $session, User $by, string $company, string $kind, ?User $consultant): TenderRosterEvent
    {
        if ($session->status !== 'announced' || $session->isGrandFinal()) {
            throw new TenderRuleException('Consultants are hired after a session\'s announcement, and not at the Grand Final.');
        }
        if (! in_array($kind, ['hire', 'hire_back', 'pass'], true) || ! in_array($company, TenderGame::COMPANIES, true)) {
            throw new TenderRuleException('Unknown commission.');
        }

        $events = $session->rosterEvents()->get();
        $winner = $session->winner;

        if ($kind === 'pass') {
            if ($company !== $winner) {
                throw new TenderRuleException('Only the winner passes its first choice.');
            }
            if ($events->where('company', $company)->whereIn('kind', ['hire', 'pass'])->isNotEmpty()) {
                throw new TenderRuleException('The winner has already chosen.');
            }

            return $session->rosterEvents()->create(['kind' => 'pass', 'company' => $company, 'created_by' => $by->id]);
        }

        $member = $consultant ? $session->game->members()->where('user_id', $consultant->id)->first() : null;
        if (! $member || ! $member->isConsultant()) {
            throw new TenderRuleException('Choose a consultant.');
        }

        if ($kind === 'hire') {
            if ($events->where('company', $company)->whereIn('kind', ['hire', 'pass'])->isNotEmpty()) {
                throw new TenderRuleException('Each company may hire one consultant per session.');
            }
            if ($company !== $winner && $events->where('company', $winner)->whereIn('kind', ['hire', 'pass'])->isEmpty()) {
                throw new TenderRuleException('The winner chooses first.');
            }
            if ($events->where('kind', 'hire')->where('user_id', $member->user_id)->isNotEmpty()) {
                throw new TenderRuleException('A consultant works for one company per session.');
            }
        } else {
            if ($company !== $winner || $member->company !== $winner) {
                throw new TenderRuleException('Only the winner may take back one of its own consultants.');
            }
            if ($events->where('kind', 'hire_back')->isNotEmpty()) {
                throw new TenderRuleException('The winner has already taken a consultant back this session.');
            }
            if ($this->companySize($session->game, $winner) >= $this->hireCap($session->game, $winner)) {
                throw new TenderRuleException('A company never goes above '.$this->hireCap($session->game, $winner).'.');
            }
        }

        return DB::transaction(function () use ($session, $member, $company, $kind, $by) {
            if ($kind === 'hire_back') {
                $member->update(['status' => 'active']);
            }

            return $session->rosterEvents()->create([
                'kind' => $kind,
                'company' => $company,
                'user_id' => $member->user_id,
                'created_by' => $by->id,
            ]);
        });
    }

    /** Piping caps a company at 6; Ophir at its starting size (consultants keep their company). */
    public function hireCap(TenderGame $game, string $company): int
    {
        return $game->rules()->isOphir()
            ? $game->members()->where('role', 'student')->where('company', $company)->count()
            : TenderFlow::MAX_COMPANY;
    }

    public function companySize(TenderGame $game, string $company): int
    {
        return $game->members()->where('role', 'student')->where('status', 'active')->where('company', $company)->count();
    }

    // ── Appeals and the lecturer's review ───────────────────────────

    public function appeal(TenderSession $session, TenderMember $member, string $body): TenderAppeal
    {
        if ($session->status !== 'announced') {
            throw new TenderRuleException('Appeals follow the announcement.');
        }
        if ($member->role !== 'student') {
            throw new TenderRuleException('Appeals come from company members.');
        }
        if (now()->greaterThan($session->appealDeadline())) {
            throw new TenderRuleException('Appeals closed at noon the day after the session.');
        }
        if ($session->appeals()->where('user_id', $member->user_id)->exists()) {
            throw new TenderRuleException('You have already appealed this session.');
        }

        return $session->appeals()->create([
            'user_id' => $member->user_id,
            'company' => $member->company,
            'body' => trim($body),
        ]);
    }

    public function review(TenderSession $session, User $lecturer, string $decision, ?string $comment): TenderSession
    {
        $this->expectStatus($session, ['announced'], 'Review the session once its result is announced.');

        if ($decision === 'queried' && blank($comment)) {
            throw new TenderRuleException('Say what the query is about.');
        }

        $session->update([
            'review_status' => $decision,
            'review_comment' => $comment,
            'reviewed_by' => $lecturer->id,
            'reviewed_at' => now(),
        ]);

        return $session;
    }

    /** The lecturer reopens one card; the session goes back to scoring until it is in again. */
    public function unlockCard(TenderJudge $judge): TenderJudge
    {
        $this->expectActiveJudge($judge);
        if (! $judge->submitted_at) {
            throw new TenderRuleException('That card is still open.');
        }

        DB::transaction(function () use ($judge) {
            $judge->scores()->update(['level_a_after' => null, 'level_b_after' => null]);
            $judge->update(['submitted_at' => null, 'reconciled_at' => null]);
            $judge->session->update([
                'status' => 'scoring',
                'reconcile_started_at' => null,
                'reconcile_criteria' => null,
                'reconciled_at' => null,
                'review_status' => null,
                'reviewed_at' => null,
            ]);
        });

        return $judge;
    }

    public function decideAppeal(TenderAppeal $appeal, User $lecturer, string $decision, ?string $note): TenderAppeal
    {
        if (! in_array($decision, TenderAppeal::DECISIONS, true)) {
            throw new TenderRuleException('Uphold or dismiss the appeal.');
        }

        $appeal->update([
            'decision' => $decision,
            'decision_note' => $note,
            'decided_by' => $lecturer->id,
            'decided_at' => now(),
        ]);

        return $appeal;
    }

    // ── Season ──────────────────────────────────────────────────────

    /**
     * @return array{wins: array{A: int, B: int}, champion: ?string, sessions: list<array<string, mixed>>}
     */
    public function standings(TenderGame $game): array
    {
        $wins = ['A' => 0, 'B' => 0];
        $rows = [];

        foreach ($game->sessions as $session) {
            if ($session->winner) {
                $wins[$session->winner]++;
            }
            $rows[] = [
                'code' => $session->code,
                'winner' => $session->winner,
                'a' => $session->result['totals']['A'] ?? null,
                'b' => $session->result['totals']['B'] ?? null,
                'approved' => $session->review_status === 'approved',
            ];
        }

        $final = $game->sessions->firstWhere('code', 'GF');

        return [
            'wins' => $wins,
            'champion' => $final?->status === 'announced' ? $final->winner : null,
            'sessions' => $rows,
        ];
    }

    // ── Internals ───────────────────────────────────────────────────

    private function compileWhenComplete(TenderSession $session): void
    {
        if ($session->status !== 'scoring') {
            return;
        }

        $judges = $session->activeJudges()->get();
        if ($judges->isNotEmpty() && $judges->every(fn (TenderJudge $j) => $j->submitted_at !== null)) {
            $session->update(['status' => 'compiled']);
        }
    }

    private function placeJudge(TenderSession $session, int $slot, array $attributes): TenderJudge
    {
        return DB::transaction(function () use ($session, $slot, $attributes) {
            $current = $session->activeJudges()->where('slot', $slot)->first();
            if ($current?->submitted_at) {
                throw new TenderRuleException('Judge '.$slot.'\'s card is already in.');
            }
            $current?->update(['replaced_at' => now()]);

            return $session->judges()->create(['slot' => $slot] + $attributes);
        });
    }

    private function expectGrandFinalStaffChange(TenderSession $session, int $slot): void
    {
        $this->expectSlot($session, $slot);
        if (! $session->staffJudged()) {
            throw new TenderRuleException($session->isGrandFinal()
                ? 'Student Judges score this Grand Final.'
                : 'Staff score only at the Grand Final.');
        }
        $this->expectStatus($session, ['setup', 'scoring'], 'Judges can only change before the cards are in.');
    }

    private function expectSlot(TenderSession $session, int $slot): void
    {
        $slots = $session->game->rules()->judgeSlots();
        if (! isset($slots[$slot])) {
            throw new TenderRuleException('There are '.count($slots).' Judge slots.');
        }
    }

    private function expectActiveJudge(TenderJudge $judge): void
    {
        if ($judge->replaced_at) {
            throw new TenderRuleException('You are no longer a Judge for this session.');
        }
    }

    private function expectStatus(TenderSession $session, array $statuses, string $message): void
    {
        if (! in_array($session->status, $statuses, true)) {
            throw new TenderRuleException($message);
        }
    }

    private function checkedLevel(TenderSession $session, string $criterion, mixed $level): ?int
    {
        if ($level === null || $level === '') {
            return null;
        }

        $rules = $session->game->rules();
        $level = (int) $level;
        $min = $rules->minScore($criterion);
        $max = $rules->maxScore($session->criteria()[$criterion] ?? 0);
        if ($level < $min || $level > $max) {
            throw new TenderRuleException(match (true) {
                $rules->isOphir() => $rules->label($criterion).' takes a mark from 0 to '.$max.'.',
                $criterion === 'compliance' => 'The compliance slide takes level 0 to 4.',
                default => $rules->label($criterion).' takes level 1 to 4.',
            });
        }

        return $level;
    }

    /** An after-reconcile level is kept only when it differs from the original. */
    private function changedLevel(TenderSession $session, string $criterion, mixed $level, ?int $original): ?int
    {
        $level = $this->checkedLevel($session, $criterion, $level);

        return $level === null || $level === $original ? null : $level;
    }

    private function savePitches(TenderJudge $judge, array $pitches): void
    {
        $consultants = $judge->session->game->members()
            ->where('status', 'consultant')
            ->pluck('user_id')
            ->all();

        foreach ($pitches as $pitch) {
            $id = (int) ($pitch['consultant_id'] ?? 0);
            if (! in_array($id, $consultants, true)) {
                throw new TenderRuleException('Only consultants pitch.');
            }

            $values = [];
            foreach (['relevant', 'credible', 'standards', 'communication'] as $field) {
                $value = (int) ($pitch[$field] ?? 0);
                if ($value < 1 || $value > 4) {
                    throw new TenderRuleException('Rate each part of a pitch 1 to 4.');
                }
                $values[$field] = $value;
            }

            $judge->pitchScores()->updateOrCreate(['consultant_id' => $id], $values);
        }
    }
}
