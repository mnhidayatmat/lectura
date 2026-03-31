<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Http\Controllers\Controller;
use App\Models\GroupMinute;
use App\Models\StudentGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkspaceMinuteController extends Controller
{
    public function store(Request $request, string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'meeting_date' => ['required', 'date'],
            'body' => ['required', 'string', 'max:20000'],
            'attachment' => ['nullable', 'file', 'max:25000', 'mimes:pdf,doc,docx'],
        ]);

        $filePath = null;
        $fileName = null;

        if ($request->hasFile('attachment')) {
            $f = $request->file('attachment');
            $filePath = $f->store("workspace/{$group->id}/minutes", 'local');
            $fileName = $f->getClientOriginalName();
        }

        GroupMinute::create([
            'student_group_id' => $group->id,
            'user_id' => $user->id,
            'title' => $request->title,
            'meeting_date' => $request->meeting_date,
            'body' => $request->body,
            'file_path' => $filePath,
            'file_name' => $fileName,
        ]);

        return back()->with('success', 'Minutes recorded.')->with('_tab', 'minutes');
    }

    public function destroy(string $tenantSlug, StudentGroup $group, GroupMinute $minute): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $minute->student_group_id !== $group->id) {
            abort(403);
        }

        $isLeader = $group->members()->where('user_id', $user->id)->where('role', 'leader')->exists();

        if ($minute->user_id !== $user->id && ! $isLeader) {
            return back()->with('error', 'Only the author or group leader can delete minutes.')->with('_tab', 'minutes');
        }

        if ($minute->file_path) {
            Storage::disk('local')->delete($minute->file_path);
        }

        $minute->delete();

        return back()->with('success', 'Minutes deleted.')->with('_tab', 'minutes');
    }
}
