<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Tender;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\TenderAppeal;
use App\Models\TenderGame;
use App\Models\TenderJudge;
use App\Models\TenderMember;
use App\Models\TenderSession;
use App\Models\User;
use App\Services\Tender\TenderCriteria;
use App\Services\Tender\TenderFlow;
use App\Services\Tender\TenderPresenter;
use App\Services\Tender\TenderRuleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The lecturer's side of the Piping Design Tender: the roster, a live view of
 * each session's cards, Approve/Query, appeals, Grand Final scoring and the
 * season standings. The lecturer observes; only unlocking a card changes a
 * session, and the game never writes a mark.
 */
class TenderGameController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        private readonly TenderFlow $flow,
        private readonly TenderPresenter $presenter,
    ) {}

    public function index(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        $games = TenderGame::where('course_id', $course->id)->withCount('members')->with('section')->latest()->get();
        $sections = $this->lecturerSections($course)->orderBy('name')->get();

        return view('tenant.tender.index', compact('course', 'games', 'sections'));
    }

    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $data = $this->validateGame($request, $course);

        $game = $this->flow->createGame($data + [
            'course_id' => $course->id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('tenant.tender.show', [$tenantSlug, $course, $game])
            ->with('success', __('tender.created'));
    }

    public function update(Request $request, string $tenantSlug, Course $course, TenderGame $game): RedirectResponse
    {
        $this->authorizeGame($course, $game);

        $game->update($this->validateGame($request, $course));

        return back()->with('success', __('tender.game_saved'));
    }

    public function show(string $tenantSlug, Course $course, TenderGame $game): View
    {
        $this->authorizeGame($course, $game);

        $game->load('sessions', 'members.user');
        $students = $this->rosterCandidates($course, $game);
        $standings = $this->flow->standings($game);
        $sizes = ['A' => $this->flow->companySize($game, 'A'), 'B' => $this->flow->companySize($game, 'B')];
        $pendingAppeals = TenderAppeal::whereIn('tender_session_id', $game->sessions->pluck('id'))->whereNull('decision')->count();

        $sections = $this->lecturerSections($course)->orderBy('name')->get();

        return view('tenant.tender.show', compact('course', 'game', 'students', 'standings', 'sizes', 'pendingAppeals', 'sections'));
    }

    /**
     * Save the whole roster at once: every listed student with their company,
     * group, role and MD flag. A student left on "Not playing" is removed.
     */
    public function updateRoster(Request $request, string $tenantSlug, Course $course, TenderGame $game): RedirectResponse
    {
        $this->authorizeGame($course, $game);

        $allowed = $this->rosterCandidates($course, $game)->pluck('id')->all();
        $data = $request->validate([
            'members' => ['array'],
            'members.*.role' => ['required', Rule::in(['none', 'student', 'leader', 'deputy'])],
            'members.*.company' => ['nullable', Rule::in(TenderGame::COMPANIES)],
            'members.*.group_no' => ['nullable', 'integer', Rule::in([1, 2])],
            'members.*.is_md' => ['nullable', 'boolean'],
            'members.*.status' => ['nullable', Rule::in(['active', 'consultant'])],
        ]);

        $rows = collect($data['members'] ?? [])->filter(fn ($row, $userId) => in_array((int) $userId, $allowed, true));

        foreach (['A', 'B'] as $company) {
            $mds = $rows->filter(fn ($r) => $r['role'] === 'student' && ($r['company'] ?? null) === $company && ! empty($r['is_md']));
            if ($mds->count() > 1) {
                return back()->withInput()->with('error', __('tender.one_md', ['company' => $game->companyName($company)]));
            }
        }

        foreach ($rows as $userId => $row) {
            if ($row['role'] === 'none') {
                $game->members()->where('user_id', $userId)->delete();

                continue;
            }

            $isStudent = $row['role'] === 'student';
            if ($isStudent && (empty($row['company']) || empty($row['group_no']))) {
                return back()->withInput()->with('error', __('tender.needs_company_group'));
            }

            $game->members()->updateOrCreate(['user_id' => (int) $userId], [
                'role' => $row['role'],
                'company' => $isStudent ? $row['company'] : null,
                'group_no' => $isStudent ? (int) $row['group_no'] : null,
                'is_md' => $isStudent && ! empty($row['is_md']),
                'status' => $isStudent ? ($row['status'] ?? 'active') : 'active',
            ]);
        }

        return back()->with('success', __('tender.roster_saved'));
    }

    public function destroy(string $tenantSlug, Course $course, TenderGame $game): RedirectResponse
    {
        $this->authorizeGame($course, $game);

        $photos = TenderJudge::whereIn('tender_session_id', $game->sessions()->pluck('id'))
            ->whereNotNull('paper_photo_path')
            ->pluck('paper_photo_path');

        $game->delete();
        Storage::disk('uploads')->delete($photos->all());

        return redirect()->route('tenant.tender.index', [$tenantSlug, $course])
            ->with('success', __('tender.deleted', ['title' => $game->title]));
    }

    public function updateWeek(Request $request, string $tenantSlug, Course $course, TenderGame $game, TenderSession $session): RedirectResponse
    {
        $this->authorizeSession($course, $game, $session);
        $data = $request->validate(['week' => ['required', 'integer', 'min:1', 'max:20']]);
        $session->update($data);

        return back()->with('success', __('tender.week_saved'));
    }

    public function session(string $tenantSlug, Course $course, TenderGame $game, TenderSession $session): View
    {
        $this->authorizeSession($course, $game, $session);

        $judges = $session->judges()->with('user', 'scores', 'pitchScores.consultant', 'session')->get();
        $sheet = in_array($session->status, ['compiled', 'reconcile', 'announced'], true)
            ? ($session->status === 'announced' && $session->result ? $session->result : $this->flow->sheet($session))
            : null;
        $events = $session->rosterEvents()->with('user')->get();
        $appeals = $session->appeals()->with('user')->get();
        $pitchRanking = $this->presenter->pitchRanking($session);
        $myJudge = $judges->first(fn (TenderJudge $j) => ! $j->replaced_at && $j->user_id === auth()->id());
        $eligible = ! $session->isGrandFinal() && in_array($session->status, ['setup', 'scoring'], true)
            ? [1 => $this->flow->eligibleJudges($session, 1), 2 => $this->flow->eligibleJudges($session, 2)]
            : null;

        return view('tenant.tender.session', compact(
            'course', 'game', 'session', 'judges', 'sheet', 'events', 'appeals', 'pitchRanking', 'myJudge', 'eligible',
        ));
    }

    public function review(Request $request, string $tenantSlug, Course $course, TenderGame $game, TenderSession $session): RedirectResponse
    {
        $this->authorizeSession($course, $game, $session);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'queried'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->flow->review($session, $request->user(), $data['decision'], $data['comment'] ?? null);

        return back()->with('success', $data['decision'] === 'approved' ? __('tender.approved') : __('tender.queried'));
    }

    public function unlock(string $tenantSlug, Course $course, TenderGame $game, TenderSession $session, TenderJudge $judge): RedirectResponse
    {
        $this->authorizeJudge($course, $game, $session, $judge);
        $this->flow->unlockCard($judge);

        return back()->with('success', __('tender.unlocked', ['name' => $judge->displayName()]));
    }

    /** The lecturer names a student Judge, under the same rules as the Leader. */
    public function assignJudge(Request $request, string $tenantSlug, Course $course, TenderGame $game, TenderSession $session): RedirectResponse
    {
        $this->authorizeSession($course, $game, $session);
        $data = $request->validate([
            'slot' => ['required', 'integer', Rule::in([1, 2])],
            'user_id' => ['required', 'integer'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $this->flow->assignJudge($session, (int) $data['slot'], $user);

        return back()->with('success', __('tender.judge_assigned', ['name' => $user->name, 'slot' => $data['slot']]));
    }

    public function staffJudge(Request $request, string $tenantSlug, Course $course, TenderGame $game, TenderSession $session): RedirectResponse
    {
        $this->authorizeSession($course, $game, $session);
        $data = $request->validate([
            'slot' => ['required', 'integer', Rule::in([1, 2])],
            'mode' => ['required', Rule::in(['me', 'guest'])],
            'guest_name' => ['required_if:mode,guest', 'nullable', 'string', 'max:120'],
        ]);

        if ($data['mode'] === 'me') {
            $this->flow->assignStaffJudge($session, (int) $data['slot'], $request->user());

            return back()->with('success', __('tender.you_judge', ['slot' => $data['slot']]));
        }

        [, $token] = $this->flow->assignGuestJudge($session, (int) $data['slot'], $data['guest_name']);

        return back()
            ->with('success', __('tender.guest_link_made'))
            ->with('guest_link', route('tender.guest.show', $token));
    }

    public function card(string $tenantSlug, Course $course, TenderGame $game, TenderSession $session): View
    {
        $this->authorizeSession($course, $game, $session);
        $judge = $this->myStaffJudge($session);

        return view('tenant.tender.card', [
            'course' => $course,
            'game' => $game,
            'session' => $session,
            'judge' => $judge->load('scores', 'pitchScores'),
            'action' => route('tenant.tender.card.save', [$tenantSlug, $course, $game, $session]),
        ]);
    }

    public function saveCard(Request $request, string $tenantSlug, Course $course, TenderGame $game, TenderSession $session): RedirectResponse
    {
        $this->authorizeSession($course, $game, $session);
        $judge = $this->myStaffJudge($session);

        self::saveCardFromForm($this->flow, $judge, $request);

        return $request->boolean('submit')
            ? redirect()->route('tenant.tender.session', [$tenantSlug, $course, $game, $session])->with('success', __('tender.card_submitted'))
            : back()->with('success', __('tender.card_saved'));
    }

    public function decideAppeal(Request $request, string $tenantSlug, Course $course, TenderGame $game, TenderSession $session, TenderAppeal $appeal): RedirectResponse
    {
        $this->authorizeSession($course, $game, $session);
        abort_unless($appeal->tender_session_id === $session->id, 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(TenderAppeal::DECISIONS)],
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->flow->decideAppeal($appeal, $request->user(), $data['decision'], $data['decision_note'] ?? null);

        return back()->with('success', __('tender.appeal_decided'));
    }

    public function paperPhoto(string $tenantSlug, Course $course, TenderGame $game, TenderSession $session, TenderJudge $judge): Response
    {
        $this->authorizeJudge($course, $game, $session, $judge);
        abort_unless($judge->paper_photo_path && Storage::disk('uploads')->exists($judge->paper_photo_path), 404);

        return Storage::disk('uploads')->response($judge->paper_photo_path);
    }

    /**
     * Approved sessions only, one row per criterion and company: the averaged
     * levels to carry into Part 1. Company criteria apply to every member;
     * group criteria are a first read beside the lecturer's own marking.
     */
    public function export(string $tenantSlug, Course $course, TenderGame $game): StreamedResponse
    {
        $this->authorizeGame($course, $game);
        $sessions = $game->sessions()->where('review_status', 'approved')->get();

        $filename = str($course->code.'-'.$game->title.'-approved-levels')->slug().'.csv';

        return response()->streamDownload(function () use ($sessions, $game) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Session', 'Criterion', 'Applies to', 'Company', 'Judge 1 level', 'Judge 2 level', 'Average level', 'Session marks', 'Max']);

            foreach ($sessions as $session) {
                foreach ($session->result['criteria'] ?? [] as $row) {
                    foreach (['A', 'B'] as $company) {
                        $cell = $row[strtolower($company)];
                        $levels = array_values(array_filter([$cell['j1'], $cell['j2']], fn ($l) => $l !== null));
                        fputcsv($out, [
                            $session->code,
                            $row['label'],
                            in_array($row['key'], TenderCriteria::COMPANY_CRITERIA, true) ? 'Company (every member)' : 'Group (first read)',
                            $game->companyName($company),
                            $cell['j1'],
                            $cell['j2'],
                            $levels ? round(array_sum($levels) / count($levels), 2) : null,
                            $cell['mark'],
                            $row['max'],
                        ]);
                    }
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Turn the web card form (scores[key][level_a|level_b|evidence],
     * pitches[user][field]) into the flow's rows; submit when asked.
     */
    public static function saveCardFromForm(TenderFlow $flow, TenderJudge $judge, Request $request): void
    {
        $request->validate([
            'scores' => ['array'],
            'scores.*.level_a' => ['nullable', 'integer'],
            'scores.*.level_b' => ['nullable', 'integer'],
            'scores.*.evidence' => ['nullable', 'string', 'max:500'],
            'pitches' => ['array'],
        ]);

        if (! $judge->declared_at) {
            if (! $request->boolean('declare')) {
                throw new TenderRuleException(__('tender.declare_first'));
            }
            $flow->declare($judge, false);
            $judge->refresh();
        }

        $scores = collect($request->input('scores', []))
            ->map(fn ($row, $key) => [
                'criterion' => (string) $key,
                'level_a' => $row['level_a'] ?? null,
                'level_b' => $row['level_b'] ?? null,
                'evidence' => $row['evidence'] ?? null,
            ])->values()->all();

        $pitches = collect($request->input('pitches', []))
            ->filter(fn ($row) => collect(['relevant', 'credible', 'standards', 'communication'])->every(fn ($f) => filled($row[$f] ?? null)))
            ->map(fn ($row, $userId) => ['consultant_id' => (int) $userId] + array_map('intval', $row))
            ->values()->all();

        $flow->saveCard($judge, $scores, $pitches);

        if ($request->boolean('submit')) {
            $flow->submit($judge->fresh());
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /** @return Collection<int, User> */
    private function validateGame(Request $request, Course $course): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'section_id' => ['nullable', 'integer', Rule::in($this->lecturerSections($course)->pluck('id'))],
            'company_a_name' => ['required', 'string', 'max:60'],
            'company_b_name' => ['required', 'string', 'max:60', 'different:company_a_name'],
        ]);
    }

    private function rosterCandidates(Course $course, TenderGame $game): Collection
    {
        $sectionIds = $game->section_id ? [$game->section_id] : $this->lecturerSections($course)->pluck('id')->all();

        return User::query()
            ->whereHas('sections', fn ($q) => $q->whereIn('sections.id', $sectionIds)->where('section_students.is_active', true))
            ->orWhereIn('id', TenderMember::where('tender_game_id', $game->id)->pluck('user_id'))
            ->orderBy('name')
            ->get();
    }

    private function myStaffJudge(TenderSession $session): TenderJudge
    {
        $judge = $session->activeJudges()->where('user_id', auth()->id())->first();
        abort_unless($judge, 403, __('tender.not_your_card'));

        return $judge;
    }

    private function authorizeGame(Course $course, TenderGame $game): void
    {
        $this->authorizeCourseAccess($course);
        abort_unless($game->course_id === $course->id, 404);
    }

    private function authorizeSession(Course $course, TenderGame $game, TenderSession $session): void
    {
        $this->authorizeGame($course, $game);
        abort_unless($session->tender_game_id === $game->id, 404);
    }

    private function authorizeJudge(Course $course, TenderGame $game, TenderSession $session, TenderJudge $judge): void
    {
        $this->authorizeSession($course, $game, $session);
        abort_unless($judge->tender_session_id === $session->id, 404);
    }
}
