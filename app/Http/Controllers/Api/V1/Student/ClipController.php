<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\ClipComment;
use App\Models\EpisodeClip;
use App\Services\Episodes\ClipInteractions;
use App\Services\Episodes\WatchCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Views, likes, saves and comments on clips (docs/mobile-api/student.md → Clip interactions).
 */
class ClipController extends Controller
{
    public function __construct(
        private readonly WatchCatalog $catalog,
        private readonly ClipInteractions $interactions,
    ) {}

    public function view(Request $request, EpisodeClip $clip): JsonResponse
    {
        $this->catalog->authorizeClip($request->user(), $clip);

        return response()->json([
            'message' => 'View recorded.',
            'data' => ['views_count' => $this->interactions->recordView($request->user(), $clip)],
        ]);
    }

    public function like(Request $request, EpisodeClip $clip): JsonResponse
    {
        return $this->toggle($request, $clip, 'likes', true, 'Liked.');
    }

    public function unlike(Request $request, EpisodeClip $clip): JsonResponse
    {
        return $this->toggle($request, $clip, 'likes', false, 'Like removed.');
    }

    public function save(Request $request, EpisodeClip $clip): JsonResponse
    {
        return $this->toggle($request, $clip, 'saves', true, 'Saved.');
    }

    public function unsave(Request $request, EpisodeClip $clip): JsonResponse
    {
        return $this->toggle($request, $clip, 'saves', false, 'Removed from saved.');
    }

    public function comments(Request $request, EpisodeClip $clip): JsonResponse
    {
        $this->catalog->authorizeClip($request->user(), $clip);
        $page = $this->interactions->comments($request->user(), $clip);

        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function replies(Request $request, EpisodeClip $clip, ClipComment $comment): JsonResponse
    {
        $this->catalog->authorizeClip($request->user(), $clip);

        return response()->json(['data' => $this->interactions->replies($request->user(), $clip, $comment)]);
    }

    public function store(Request $request, EpisodeClip $clip): JsonResponse
    {
        $this->catalog->authorizeClip($request->user(), $clip);
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:500'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'message' => 'Comment posted.',
            'data' => $this->interactions->post(
                $request->user(),
                $clip,
                $validated['body'],
                isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            ),
        ], 201);
    }

    public function destroy(Request $request, EpisodeClip $clip, ClipComment $comment): JsonResponse
    {
        $this->catalog->authorizeClip($request->user(), $clip);

        return response()->json([
            'message' => 'Comment deleted.',
            'data' => ['comments_count' => $this->interactions->delete($request->user(), $clip, $comment)],
        ]);
    }

    public function likeComment(Request $request, EpisodeClip $clip, ClipComment $comment): JsonResponse
    {
        $this->catalog->authorizeClip($request->user(), $clip);

        return response()->json(['message' => 'Liked.', 'data' => $this->interactions->likeComment($request->user(), $clip, $comment, true)]);
    }

    public function unlikeComment(Request $request, EpisodeClip $clip, ClipComment $comment): JsonResponse
    {
        $this->catalog->authorizeClip($request->user(), $clip);

        return response()->json(['message' => 'Like removed.', 'data' => $this->interactions->likeComment($request->user(), $clip, $comment, false)]);
    }

    private function toggle(Request $request, EpisodeClip $clip, string $kind, bool $on, string $message): JsonResponse
    {
        $this->catalog->authorizeClip($request->user(), $clip);

        return response()->json(['message' => $message, 'data' => $this->interactions->toggle($request->user(), $clip, $kind, $on)]);
    }
}
