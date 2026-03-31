<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Http\Controllers\Controller;
use App\Models\GroupSleepingPartnerReport;
use App\Models\StudentGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceReportController extends Controller
{
    /**
     * Submit an anonymous sleeping partner report.
     */
    public function store(Request $request, string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403);
        }

        $memberIds = $group->members()->where('user_id', '!=', $user->id)->pluck('user_id')->toArray();

        $request->validate([
            'reported_user_id' => ['required', 'integer', 'in:' . implode(',', $memberIds)],
            'description' => ['required', 'string', 'min:20', 'max:500'],
        ]);

        // Reporter identity is deliberately NOT stored
        GroupSleepingPartnerReport::create([
            'student_group_id' => $group->id,
            'reported_user_id' => $request->reported_user_id,
            'description' => $request->description,
        ]);

        return back()->with('success', 'Your report has been submitted anonymously.')->with('_tab', 'members');
    }
}
