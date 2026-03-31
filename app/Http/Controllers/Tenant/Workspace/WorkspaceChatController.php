<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Workspace;

use App\Events\GroupMessageSent;
use App\Http\Controllers\Controller;
use App\Models\StudentGroup;
use App\Models\StudentGroupPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);
    }
}
