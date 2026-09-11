<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workspace;

use App\Events\GroupMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Workspace\ChatMessageResource;
use App\Models\StudentGroup;
use App\Models\StudentGroupPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceChatController extends Controller
{
    use AuthorizesGroupMembership;

    public const PAGE_SIZE = 50;

    /**
     * Newest page by default; `before_id` pages back through history,
     * `after_id` returns messages newer than the given id (for polling).
     */
    public function index(Request $request, StudentGroup $group): JsonResponse
    {
        $this->authorizeMember($group, $request->user());

        $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = StudentGroupPost::where('student_group_id', $group->id)
            ->whereNull('parent_id')
            ->with('user:id,name,avatar_url');

        if ($request->filled('after_id')) {
            $messages = $query->where('id', '>', $request->integer('after_id'))
                ->orderBy('id')
                ->limit(self::PAGE_SIZE + 1)
                ->get();

            $hasMore = $messages->count() > self::PAGE_SIZE;
            $messages = $messages->take(self::PAGE_SIZE)->values();
        } else {
            if ($request->filled('before_id')) {
                $query->where('id', '<', $request->integer('before_id'));
            }

            $messages = $query->orderByDesc('id')
                ->limit(self::PAGE_SIZE + 1)
                ->get();

            $hasMore = $messages->count() > self::PAGE_SIZE;
            $messages = $messages->take(self::PAGE_SIZE)->reverse()->values();
        }

        return ChatMessageResource::collection($messages)
            ->additional(['meta' => ['has_more' => $hasMore, 'limit' => self::PAGE_SIZE]])
            ->response();
    }

    public function store(Request $request, StudentGroup $group): JsonResponse
    {
        $user = $request->user();
        $this->authorizeMember($group, $user);

        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = StudentGroupPost::create([
            'student_group_id' => $group->id,
            'user_id' => $user->id,
            'parent_id' => null,
            'body' => $request->body,
        ]);

        $message->load('user:id,name,avatar_url');

        broadcast(new GroupMessageSent($message))->toOthers();

        return response()->json([
            'message' => 'Message sent.',
            'data' => new ChatMessageResource($message),
        ], 201);
    }

    public function update(Request $request, StudentGroup $group, StudentGroupPost $message): JsonResponse
    {
        $this->ensureGroupInTenant($group);

        if ((int) $message->student_group_id !== $group->id || (int) $message->user_id !== (int) $request->user()->id) {
            abort(403, 'You can only edit your own messages.');
        }

        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message->update(['body' => $request->body]);
        $message->load('user:id,name,avatar_url');

        return response()->json([
            'message' => 'Message updated.',
            'data' => new ChatMessageResource($message),
        ]);
    }

    public function destroy(Request $request, StudentGroup $group, StudentGroupPost $message): JsonResponse
    {
        $this->ensureGroupInTenant($group);

        if ((int) $message->student_group_id !== $group->id || (int) $message->user_id !== (int) $request->user()->id) {
            abort(403, 'You can only delete your own messages.');
        }

        $message->delete();

        return response()->json([
            'message' => 'Message deleted.',
            'data' => ['id' => $message->id, 'deleted' => true],
        ]);
    }
}
