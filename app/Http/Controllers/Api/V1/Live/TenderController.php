<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Live;

use App\Http\Controllers\Controller;
use App\Models\TenderGame;
use App\Models\TenderJudge;
use App\Models\TenderMember;
use App\Models\TenderSession;
use App\Models\User;
use App\Services\Tender\TenderFlow;
use App\Services\Tender\TenderPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Piping Design Tender game in Lectura Go: Judges score, the Leader runs and
 * compiles the session, the losing MD names the retrenched student and members
 * appeal. Anyone outside a game's roster gets a 404.
 */
class TenderController extends Controller
{
    public function __construct(
        private readonly TenderFlow $flow,
        private readonly TenderPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $members = TenderMember::where('user_id', $request->user()->id)
            ->whereHas('game')
            ->with('game.course')
            ->get();

        return response()->json([
            'data' => $members->map(fn (TenderMember $m) => $this->presenter->gameSummary($m->game, $m))->values(),
        ]);
    }

    public function game(Request $request, TenderGame $game): JsonResponse
    {
        return response()->json(['data' => $this->presenter->game($game, $this->member($request, $game))]);
    }

    public function session(Request $request, TenderSession $session): JsonResponse
    {
        return $this->sessionResponse($request, $session);
    }

    // ── Leader ──────────────────────────────────────────────────────

    public function setup(Request $request, TenderSession $session): JsonResponse
    {
        $this->leader($request, $session);
        $data = $request->validate([
            'first_company' => ['sometimes', 'nullable', Rule::in(TenderGame::COMPANIES)],
            'lead_group_a' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2])],
            'lead_group_b' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2])],
            'change_order_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $this->flow->setup($session, $data);

        return $this->sessionResponse($request, $session, 'Session updated.');
    }

    public function assignJudge(Request $request, TenderSession $session): JsonResponse
    {
        $this->leader($request, $session);
        $data = $request->validate([
            'slot' => ['required', 'integer', Rule::in([1, 2])],
            'user_id' => ['required', 'integer'],
        ]);

        $this->flow->assignJudge($session, (int) $data['slot'], User::findOrFail($data['user_id']));

        return $this->sessionResponse($request, $session, 'Judge assigned.');
    }

    public function start(Request $request, TenderSession $session): JsonResponse
    {
        $this->leader($request, $session);
        $this->flow->start($session);

        return $this->sessionResponse($request, $session, 'Scoring has started.');
    }

    public function startReconcile(Request $request, TenderSession $session): JsonResponse
    {
        $this->leader($request, $session);
        $this->flow->startReconcile($session);

        return $this->sessionResponse($request, $session, 'Reconcile round started: 2 minutes.');
    }

    public function endReconcile(Request $request, TenderSession $session): JsonResponse
    {
        $this->leader($request, $session);
        $this->flow->endReconcile($session);

        return $this->sessionResponse($request, $session, 'Reconcile round closed.');
    }

    public function announce(Request $request, TenderSession $session): JsonResponse
    {
        $this->leader($request, $session);
        $data = $request->validate([
            'sudden_death_winner' => ['nullable', Rule::in(TenderGame::COMPANIES)],
        ]);

        $this->flow->announce($session, $data['sudden_death_winner'] ?? null);

        return $this->sessionResponse($request, $session, 'Result announced.');
    }

    public function hire(Request $request, TenderSession $session): JsonResponse
    {
        $this->leader($request, $session);
        $data = $request->validate([
            'company' => ['required', Rule::in(TenderGame::COMPANIES)],
            'kind' => ['required', Rule::in(['hire', 'hire_back', 'pass'])],
            'consultant_id' => ['nullable', 'integer'],
        ]);

        $consultant = isset($data['consultant_id']) ? User::find($data['consultant_id']) : null;
        $this->flow->recordHire($session, $request->user(), $data['company'], $data['kind'], $consultant);

        return $this->sessionResponse($request, $session, 'Recorded.');
    }

    public function paperCard(Request $request, TenderSession $session, TenderJudge $judge): JsonResponse
    {
        $this->leader($request, $session);
        abort_unless($judge->tender_session_id === $session->id, 404);

        $request->validate([
            'scores' => ['required'],
            'pitches' => ['nullable'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ]);

        // Multipart bodies carry the arrays as JSON strings.
        $scores = $this->arrayInput($request, 'scores');
        $pitches = $this->arrayInput($request, 'pitches');
        $path = $request->file('photo')?->store('tender/paper-cards', 'uploads');

        $this->flow->enterPaperCard($judge, $request->user(), $scores, $pitches, $path);

        return $this->sessionResponse($request, $session, 'Paper card entered.');
    }

    // ── Judge ───────────────────────────────────────────────────────

    public function declare(Request $request, TenderSession $session): JsonResponse
    {
        $data = $request->validate(['conflict' => ['required', 'boolean']]);
        $this->flow->declare($this->judge($request, $session), (bool) $data['conflict']);

        return $this->sessionResponse($request, $session, $data['conflict']
            ? 'Conflict noted. The Leader will name a substitute from your company.'
            : 'Declaration confirmed.');
    }

    public function saveCard(Request $request, TenderSession $session): JsonResponse
    {
        $data = $request->validate([
            'scores' => ['present', 'array'],
            'scores.*.criterion' => ['required', 'string'],
            'scores.*.level_a' => ['nullable', 'integer'],
            'scores.*.level_b' => ['nullable', 'integer'],
            'scores.*.evidence' => ['nullable', 'string', 'max:500'],
            'pitches' => ['sometimes', 'array'],
            'pitches.*.consultant_id' => ['required', 'integer'],
            'pitches.*.relevant' => ['required', 'integer'],
            'pitches.*.credible' => ['required', 'integer'],
            'pitches.*.standards' => ['required', 'integer'],
            'pitches.*.communication' => ['required', 'integer'],
        ]);

        $this->flow->saveCard($this->judge($request, $session), $data['scores'], $data['pitches'] ?? []);

        return $this->sessionResponse($request, $session, 'Saved.');
    }

    public function submitCard(Request $request, TenderSession $session): JsonResponse
    {
        $this->flow->submit($this->judge($request, $session));

        return $this->sessionResponse($request, $session, 'Card handed to the Leader.');
    }

    public function reconciled(Request $request, TenderSession $session): JsonResponse
    {
        $this->flow->finishReconcileFor($this->judge($request, $session));

        return $this->sessionResponse($request, $session, 'Reconcile done.');
    }

    // ── Managing Director and members ───────────────────────────────

    public function retrench(Request $request, TenderSession $session): JsonResponse
    {
        $member = $this->member($request, $session->game);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->flow->retrench($session, $member, User::findOrFail($data['user_id']), $data['reason']);

        return $this->sessionResponse($request, $session, 'The Leader has been told. Your company announces the name.');
    }

    public function appeal(Request $request, TenderSession $session): JsonResponse
    {
        $member = $this->member($request, $session->game);
        $data = $request->validate(['body' => ['required', 'string', 'min:20', 'max:3000']]);

        $this->flow->appeal($session, $member, $data['body']);

        return $this->sessionResponse($request, $session, 'Appeal sent to the lecturer.');
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function sessionResponse(Request $request, TenderSession $session, ?string $message = null): JsonResponse
    {
        $session = $session->fresh();
        $member = $this->member($request, $session->game);
        $data = $this->presenter->session($session, $member, $request->user());

        return response()->json($message ? ['message' => $message, 'data' => $data] : ['data' => $data]);
    }

    private function member(Request $request, TenderGame $game): TenderMember
    {
        $member = $game->members()->where('user_id', $request->user()->id)->first();
        abort_unless($member, 404, 'Tender game not found.');

        return $member;
    }

    private function leader(Request $request, TenderSession $session): TenderMember
    {
        $member = $this->member($request, $session->game);
        abort_unless($member->runsSessions(), 403, 'Only the Leader or deputy runs the session.');

        return $member;
    }

    private function judge(Request $request, TenderSession $session): TenderJudge
    {
        $this->member($request, $session->game);
        $judge = $session->activeJudges()->where('user_id', $request->user()->id)->first();
        abort_unless($judge, 403, 'You are not a Judge for this session.');

        return $judge;
    }

    private function arrayInput(Request $request, string $key): array
    {
        $value = $request->input($key);
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
