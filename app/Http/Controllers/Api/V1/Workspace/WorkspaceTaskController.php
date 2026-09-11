<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Workspace\GroupTaskResource;
use App\Models\GroupTask;
use App\Models\StudentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceTaskController extends Controller
{
    use AuthorizesGroupMembership;

    /** Fields only the creator or the group leader may change. */
    private const CONTENT_FIELDS = ['title', 'description', 'assigned_to', 'start_date', 'due_date'];

    public function index(Request $request, StudentGroup $group): JsonResponse
    {
        $this->authorizeMember($group, $request->user());

        $group->load('members');

        $tasks = $group->tasks()
            ->with(['assignee:id,name', 'creator:id,name'])
            ->get()
            ->each(fn (GroupTask $task) => $task->setRelation('group', $group));

        return GroupTaskResource::collection($tasks)
            ->additional(['meta' => [
                'total' => $tasks->count(),
                'todo' => $tasks->where('status', 'todo')->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'done' => $tasks->where('status', 'done')->count(),
                'overdue' => $tasks->filter(fn (GroupTask $task) => $task->isOverdue())->count(),
            ]])
            ->response();
    }

    public function store(Request $request, StudentGroup $group): JsonResponse
    {
        $user = $request->user();
        $this->authorizeMember($group, $user);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', Rule::in($this->memberIds($group))],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $task = GroupTask::create([
            'student_group_id' => $group->id,
            'title' => $validated['title'],
            'description' => $this->cleanDescription($request->input('description')),
            'assigned_to' => $validated['assigned_to'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => 'todo',
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Task added.',
            'data' => new GroupTaskResource($this->prepare($task, $group)),
        ], 201);
    }

    /**
     * Any member may change `status`; the other fields need the creator or the group leader.
     * Every field is optional — send `null` to clear the description, assignee or a date.
     */
    public function update(Request $request, StudentGroup $group, GroupTask $task): JsonResponse
    {
        $user = $request->user();
        $this->authorizeTask($request, $group, $task);

        $editsContent = $request->hasAny(self::CONTENT_FIELDS);

        if ($editsContent && (int) $task->created_by !== (int) $user->id && ! $this->isLeader($group, $user)) {
            abort(403, 'Only the task creator or group leader can edit tasks.');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::in($this->memberIds($group))],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'required', 'in:todo,in_progress,done'],
        ]);

        if (! $request->hasAny([...self::CONTENT_FIELDS, 'status'])) {
            throw ValidationException::withMessages(['title' => 'Send at least one field to update.']);
        }

        $changes = [];
        foreach (['title', 'assigned_to', 'start_date', 'due_date', 'status'] as $field) {
            if ($request->has($field)) {
                $changes[$field] = $validated[$field] ?? null;
            }
        }
        if ($request->has('description')) {
            $changes['description'] = $this->cleanDescription($request->input('description'));
        }

        $this->assertDateOrder(
            array_key_exists('start_date', $changes) ? $changes['start_date'] : $task->start_date,
            array_key_exists('due_date', $changes) ? $changes['due_date'] : $task->due_date,
        );

        $task->update($changes);

        return response()->json([
            'message' => 'Task updated.',
            'data' => new GroupTaskResource($this->prepare($task, $group)),
        ]);
    }

    public function destroy(Request $request, StudentGroup $group, GroupTask $task): JsonResponse
    {
        $user = $request->user();
        $this->authorizeTask($request, $group, $task);

        if ((int) $task->created_by !== (int) $user->id && ! $this->isLeader($group, $user)) {
            abort(403, 'Only the task creator or group leader can delete tasks.');
        }

        $task->delete();

        return response()->json([
            'message' => 'Task deleted.',
            'data' => ['id' => $task->id],
        ]);
    }

    private function authorizeTask(Request $request, StudentGroup $group, GroupTask $task): void
    {
        $this->ensureGroupInTenant($group);

        if (! $group->isMember($request->user()->id) || (int) $task->student_group_id !== $group->id) {
            abort(403, 'You cannot manage this task.');
        }
    }

    /**
     * @return array<int, int>
     */
    private function memberIds(StudentGroup $group): array
    {
        return $group->members()->pluck('user_id')->all();
    }

    private function cleanDescription(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function assertDateOrder(mixed $start, mixed $due): void
    {
        if ($start === null || $due === null) {
            return;
        }

        if (Carbon::parse($due)->startOfDay()->lt(Carbon::parse($start)->startOfDay())) {
            throw ValidationException::withMessages([
                'due_date' => 'The due date must be a date after or equal to start date.',
            ]);
        }
    }

    private function prepare(GroupTask $task, StudentGroup $group): GroupTask
    {
        $group->loadMissing('members');

        return $task->load(['assignee:id,name', 'creator:id,name'])->setRelation('group', $group);
    }
}
