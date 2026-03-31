<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Events\GroupMessageSent;
use App\Http\Controllers\Controller;
use App\Models\StudentGroup;
use App\Models\StudentGroupPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceChatController extends Controller
{
    /**
     * Load chat history (JSON — called via fetch on page load).
     */
    public function index(string $tenantSlug, StudentGroup $group): JsonResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $messages = StudentGroupPost::where('student_group_id', $group->id)
            ->whereNull('parent_id') // top-level only — posts without parent are chat messages
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'user_id' => $m->user_id,
                'user_name' => $m->user->name,
                'user_initial' => strtoupper(substr($m->user->name, 0, 1)),
                'sent_at' => $m->created_at->format('H:i'),
                'is_mine' => $m->user_id === $user->id,
                'is_edited' => $m->updated_at->gt($m->created_at),
                'deleted' => false,
            ]);

        return response()->json($messages);
    }

    /**
     * Send a new chat message.
     */
    public function store(Request $request, string $tenantSlug, StudentGroup $group): JsonResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = StudentGroupPost::create([
            'student_group_id' => $group->id,
            'user_id' => $user->id,
            'parent_id' => null,
            'body' => $request->body,
        ]);

        $message->load('user:id,name');

        broadcast(new GroupMessageSent($message))->toOthers();

        return response()->json([
            'id' => $message->id,
            'body' => $message->body,
            'user_id' => $message->user_id,
            'user_name' => $message->user->name,
            'user_initial' => strtoupper(substr($message->user->name, 0, 1)),
            'sent_at' => $message->created_at->format('H:i'),
            'is_mine' => true,
            'is_edited' => false,
            'deleted' => false,
        ]);
    }

    /**
     * Edit own chat message.
     */
    public function update(Request $request, string $tenantSlug, StudentGroup $group, StudentGroupPost $message): JsonResponse
    {
        $user = auth()->user();

        if ($message->student_group_id !== $group->id || $message->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message->update(['body' => $request->body]);

        return response()->json([
            'body' => $message->body,
            'is_edited' => true,
        ]);
    }

    /**
     * Delete own chat message.
     */
    public function destroy(string $tenantSlug, StudentGroup $group, StudentGroupPost $message): JsonResponse
    {
        $user = auth()->user();

        if ($message->student_group_id !== $group->id || $message->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $message->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Heartbeat: mark current user as online and return list of online member IDs.
     */
    public function presence(string $tenantSlug, StudentGroup $group): JsonResponse
    {
        $user = auth()->user();

        if (! $group->isMember($user->id)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Mark this user as online for 35 seconds
        Cache::put("chat_online:{$group->id}:{$user->id}", true, 35);

        // Check which group members are online
        $memberIds = $group->members()->pluck('user_id');
        $onlineIds = [];

        foreach ($memberIds as $memberId) {
            if (Cache::has("chat_online:{$group->id}:{$memberId}")) {
                $onlineIds[] = $memberId;
            }
        }

        return response()->json($onlineIds);
    }
}
