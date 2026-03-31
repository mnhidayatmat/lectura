<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Http\Controllers\Controller;
use App\Models\GroupVote;
use App\Models\GroupVoteRound;
use App\Models\StudentGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceVoteController extends Controller
{
    /**
     * Start a new voting round.
     */
    public function start(string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403);
        }

        // Only one open round at a time
        if ($group->voteRounds()->where('status', 'open')->exists()) {
            return back()->with('error', 'A voting round is already in progress.');
        }

        GroupVoteRound::create([
            'student_group_id' => $group->id,
            'started_by' => $user->id,
            'status' => 'open',
        ]);

        return back()->with('success', 'Voting started! All members can now cast their vote.');
    }

    /**
     * Cast a vote.
     */
    public function cast(Request $request, string $tenantSlug, StudentGroup $group, GroupVoteRound $round): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $round->student_group_id !== $group->id) {
            abort(403);
        }

        if (! $round->isOpen()) {
            return back()->with('error', 'Voting has already closed.');
        }

        if ($round->hasVoted($user->id)) {
            return back()->with('error', 'You have already voted.');
        }

        $memberIds = $group->members()->pluck('user_id')->toArray();

        $request->validate([
            'nominee_id' => ['required', 'integer', 'in:' . implode(',', $memberIds)],
        ]);

        GroupVote::create([
            'vote_round_id' => $round->id,
            'voter_id' => $user->id,
            'nominee_id' => $request->nominee_id,
        ]);

        // Auto-close when all members have voted
        $totalMembers = $group->members()->count();
        if ($round->votes()->count() >= $totalMembers) {
            $this->closeRound($round, $group);
        }

        return back()->with('success', 'Your vote has been cast.');
    }

    /**
     * Manually close voting and reveal results.
     */
    public function close(string $tenantSlug, StudentGroup $group, GroupVoteRound $round): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $round->student_group_id !== $group->id) {
            abort(403);
        }

        if ($round->started_by !== $user->id) {
            return back()->with('error', 'Only the member who started voting can close it.');
        }

        $this->closeRound($round, $group);

        return back()->with('success', 'Voting closed. Results revealed!');
    }

    /**
     * Delete a closed voting round.
     */
    public function destroy(string $tenantSlug, StudentGroup $group, GroupVoteRound $round): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $round->student_group_id !== $group->id) {
            abort(403);
        }

        if ($round->isOpen()) {
            return back()->with('error', 'Cannot delete an active voting round. Close it first.');
        }

        $round->votes()->delete();
        $round->delete();

        return back()->with('success', 'Voting round deleted.');
    }

    private function closeRound(GroupVoteRound $round, StudentGroup $group): void
    {
        $votes = $round->votes()->get();
        $tally = $votes->groupBy('nominee_id')->map->count()->sortDesc();

        $winnerId = null;

        if ($tally->isNotEmpty()) {
            $maxVotes = $tally->first();
            $topCandidates = $tally->filter(fn ($count) => $count === $maxVotes)->keys();

            if ($topCandidates->count() === 1) {
                $winnerId = $topCandidates->first();
            } else {
                // Tie: retain existing leader if tied, else earliest-joined
                $currentLeader = $group->members()->where('role', 'leader')->first();
                if ($currentLeader && $topCandidates->contains($currentLeader->user_id)) {
                    $winnerId = $currentLeader->user_id;
                } else {
                    $winnerId = $group->members()
                        ->whereIn('user_id', $topCandidates->toArray())
                        ->orderBy('joined_at')
                        ->value('user_id');
                }
            }
        }

        $round->update([
            'status' => 'closed',
            'winner_id' => $winnerId,
            'closed_at' => now(),
        ]);

        // Update group leader
        if ($winnerId) {
            $group->members()->update(['role' => 'member']);
            $group->members()->where('user_id', $winnerId)->update(['role' => 'leader']);
        }
    }
}
