<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Tender;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\TenderJudge;
use App\Models\TenderSession;
use App\Services\Tender\TenderFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The two tender pages outside the lecturer's course menu: the projector view
 * (the Leader and deputy, or a course lecturer) and the Grand Final card for an
 * invited staff member, whose one-time link is the only authorization.
 */
class TenderPublicController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(private readonly TenderFlow $flow) {}

    public function projector(string $tenantSlug, TenderSession $session): View
    {
        $this->authorizeProjector($session);

        return view('tenant.tender.projector', [
            'session' => $session,
            'game' => $session->game,
            'stateUrl' => route('tenant.tender.projector.state', [$tenantSlug, $session]),
        ]);
    }

    public function projectorState(string $tenantSlug, TenderSession $session): JsonResponse
    {
        $this->authorizeProjector($session);
        $session->refresh();
        $game = $session->game;
        $announced = $session->status === 'announced';

        return response()->json([
            'status' => $session->status,
            'label' => $session->label(),
            'change_order_note' => $session->change_order_note,
            'judges_in' => $session->activeJudges()->whereNotNull('submitted_at')->count(),
            'judges' => $session->activeJudges()->count(),
            'winner' => $announced ? $game->companyName($session->winner) : null,
            'tie_break' => $announced ? $session->tie_break : null,
            'totals' => $announced ? [
                ['name' => $game->company_a_name, 'mark' => $session->result['totals']['A'] ?? null, 'won' => $session->winner === 'A'],
                ['name' => $game->company_b_name, 'mark' => $session->result['totals']['B'] ?? null, 'won' => $session->winner === 'B'],
            ] : null,
        ]);
    }

    public function guest(string $token): View
    {
        $judge = $this->guestJudge($token);

        return view('tender.guest', [
            'judge' => $judge->load('scores', 'pitchScores'),
            'session' => $judge->session,
            'game' => $judge->session->game,
            'action' => route('tender.guest.save', $token),
        ]);
    }

    public function guestSave(Request $request, string $token): RedirectResponse
    {
        $judge = $this->guestJudge($token);
        TenderGameController::saveCardFromForm($this->flow, $judge, $request);

        return back()->with('success', $request->boolean('submit') ? __('tender.card_submitted') : __('tender.card_saved'));
    }

    private function guestJudge(string $token): TenderJudge
    {
        $judge = $this->flow->guestJudge($token);
        abort_unless($judge, 404);

        // No tenant is bound on this route; bind the judge's own so scoped models resolve.
        app()->instance('current_tenant', $judge->session->game->tenant);

        return $judge;
    }

    private function authorizeProjector(TenderSession $session): void
    {
        $member = $session->game->members()->where('user_id', auth()->id())->first();
        if ($member?->runsSessions()) {
            return;
        }

        $this->authorizeCourseAccess($session->game->course);
    }
}
