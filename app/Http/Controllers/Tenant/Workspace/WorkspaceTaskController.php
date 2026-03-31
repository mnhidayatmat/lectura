<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Http\Controllers\Controller;
use App\Models\GroupTask;
use App\Models\StudentGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceTaskController extends Controller
{
    public function store(Request $request, string $tenantSlug, StudentGroup $group): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            abort(403);
        }

        $memberIds = $group->members()->pluck('user_id')->toArray();

        $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'assigned_to' => ['nullable', 'integer', 'in:' . implode(',', $memberIds)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        GroupTask::create([
            'student_group_id' => $group->id,
            'title' => $request->title,
            'assigned_to' => $request->assigned_to,
            'start_date' => $request->start_date,
            'due_date' => $request->due_date,
            'status' => 'todo',
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Task added.')->with('_tab', 'tasks');
    }

    public function update(Request $request, string $tenantSlug, StudentGroup $group, GroupTask $task): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $task->student_group_id !== $group->id) {
            abort(403);
        }

        $request->validate([
            'status' => ['required', 'in:todo,in_progress,done'],
        ]);

        $task->update(['status' => $request->status]);

        return back()->with('success', 'Task updated.')->with('_tab', 'tasks');
    }

    public function destroy(string $tenantSlug, StudentGroup $group, GroupTask $task): RedirectResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id) || $task->student_group_id !== $group->id) {
            abort(403);
        }

        $isLeader = $group->members()->where('user_id', $user->id)->where('role', 'leader')->exists();

        if ($task->created_by !== $user->id && ! $isLeader) {
            return back()->with('error', 'Only the task creator or group leader can delete tasks.')->with('_tab', 'tasks');
        }

        $task->delete();

        return back()->with('success', 'Task deleted.')->with('_tab', 'tasks');
    }
}
