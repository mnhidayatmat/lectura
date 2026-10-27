<?php

declare(strict_types=1);

namespace App\Services\Episodes;

use App\Models\ClipComment;
use App\Models\EpisodeClip;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Views, likes, saves and comments on clips (docs/mobile-api/student.md → Clip
 * interactions). The counters on `episode_clips` and `clip_comments` move inside
 * the same transaction as the row they count, so they never drift.
 */
final class ClipInteractions
{
    /** A looping feed would count every loop; one view per student per window. */
    public const VIEW_WINDOW_MINUTES = 30;

    public function __construct(private readonly WatchCatalog $catalog) {}

    /**
     * Counts a play (once per window) and, with [completed], marks the clip watched
     * the first time the student plays it to its end.
     */
    public function recordView(User $user, EpisodeClip $clip, bool $completed = false): int
    {
        DB::transaction(function () use ($user, $clip, $completed) {
            $view = DB::table('episode_clip_views')
                ->where('episode_clip_id', $clip->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $counts = ! ($view && $view->last_viewed_at && now()->subMinutes(self::VIEW_WINDOW_MINUTES)->lt($view->last_viewed_at));

            if ($view) {
                $changes = $counts ? ['plays' => $view->plays + 1, 'last_viewed_at' => now()] : [];
                if ($completed && $view->completed_at === null) {
                    $changes['completed_at'] = now();
                }
                if ($changes !== []) {
                    DB::table('episode_clip_views')->where('id', $view->id)->update($changes + ['updated_at' => now()]);
                }
            } else {
                DB::table('episode_clip_views')->insert([
                    'episode_clip_id' => $clip->id,
                    'user_id' => $user->id,
                    'plays' => 1,
                    'last_viewed_at' => now(),
                    'completed_at' => $completed ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($counts) {
                EpisodeClip::withoutGlobalScopes()->whereKey($clip->id)->increment('views_count');
            }
        });

        return (int) EpisodeClip::withoutGlobalScopes()->whereKey($clip->id)->value('views_count');
    }

    /**
     * Marks the clip watched without counting a view (the web feed counts none); a row it
     * creates has no `last_viewed_at`, so the app's next view still counts.
     */
    public function markWatched(User $user, EpisodeClip $clip): void
    {
        DB::table('episode_clip_views')->insertOrIgnore([
            'episode_clip_id' => $clip->id,
            'user_id' => $user->id,
            'plays' => 0,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('episode_clip_views')
            ->where('episode_clip_id', $clip->id)
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->update(['completed_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Adds or removes the user's like or save; repeating either changes nothing.
     *
     * @param  'likes'|'saves'  $kind
     */
    public function toggle(User $user, EpisodeClip $clip, string $kind, bool $on): array
    {
        $table = "episode_clip_{$kind}";
        $counter = "{$kind}_count";

        DB::transaction(function () use ($user, $clip, $table, $counter, $on) {
            $exists = DB::table($table)->where('episode_clip_id', $clip->id)->where('user_id', $user->id)->lockForUpdate()->exists();
            if ($on && ! $exists) {
                DB::table($table)->insert(['episode_clip_id' => $clip->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
                EpisodeClip::withoutGlobalScopes()->whereKey($clip->id)->increment($counter);
            } elseif (! $on && $exists) {
                DB::table($table)->where('episode_clip_id', $clip->id)->where('user_id', $user->id)->delete();
                $this->decrement(EpisodeClip::withoutGlobalScopes()->whereKey($clip->id), $counter);
            }
        });

        $flag = $kind === 'likes' ? 'liked' : 'saved';

        return [$flag => $on, $counter => (int) EpisodeClip::withoutGlobalScopes()->whereKey($clip->id)->value($counter)];
    }

    public function comments(User $user, EpisodeClip $clip): LengthAwarePaginator
    {
        $page = ClipComment::where('episode_clip_id', $clip->id)
            ->whereNull('parent_id')
            ->withCount('replies')
            ->with(['author', 'replyTo'])
            ->orderByDesc('likes_count')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        $presenter = $this->presenter($user, $clip, $page->getCollection());
        $page->setCollection($page->getCollection()->map($presenter));

        return $page;
    }

    /** @return array<int, array<string, mixed>> */
    public function replies(User $user, EpisodeClip $clip, ClipComment $comment): array
    {
        $this->ensureCommentOf($clip, $comment);

        $replies = ClipComment::where('parent_id', $comment->id)
            ->with(['author', 'replyTo'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $replies->map($this->presenter($user, $clip, $replies))->values()->all();
    }

    public function post(User $user, EpisodeClip $clip, string $body, ?int $parentId): array
    {
        $parent = null;
        $replyTo = null;
        if ($parentId !== null) {
            $parent = ClipComment::where('episode_clip_id', $clip->id)->whereKey($parentId)->first();
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'That comment is no longer there.']);
            }
            // Replies stay one level deep: answering a reply joins its thread and names its author.
            if ($parent->parent_id !== null) {
                $replyTo = $parent->user_id;
                $parent = ClipComment::find($parent->parent_id) ?? $parent;
            }
        }

        $comment = DB::transaction(function () use ($user, $clip, $body, $parent, $replyTo) {
            $comment = ClipComment::create([
                'tenant_id' => $clip->tenant_id,
                'episode_clip_id' => $clip->id,
                'user_id' => $user->id,
                'parent_id' => $parent?->id,
                'reply_to_user_id' => $replyTo,
                'body' => $body,
            ]);
            EpisodeClip::withoutGlobalScopes()->whereKey($clip->id)->increment('comments_count');

            return $comment;
        });

        $comment->load(['author', 'replyTo'])->loadCount('replies');

        return $this->presenter($user, $clip, collect([$comment]))($comment);
    }

    public function delete(User $user, EpisodeClip $clip, ClipComment $comment): int
    {
        $this->ensureCommentOf($clip, $comment);

        if ((int) $comment->user_id !== (int) $user->id && ! $this->catalog->teachesCourse($user, (int) $clip->course_id)) {
            abort(403, 'You can only delete your own comments.');
        }

        DB::transaction(function () use ($clip, $comment) {
            $removed = 1;
            if ($comment->parent_id === null) {
                $removed += ClipComment::where('parent_id', $comment->id)->count();
                ClipComment::where('parent_id', $comment->id)->delete();
            }
            $comment->delete();
            $this->decrement(EpisodeClip::withoutGlobalScopes()->whereKey($clip->id), 'comments_count', $removed);
        });

        return (int) EpisodeClip::withoutGlobalScopes()->whereKey($clip->id)->value('comments_count');
    }

    public function likeComment(User $user, EpisodeClip $clip, ClipComment $comment, bool $on): array
    {
        $this->ensureCommentOf($clip, $comment);

        DB::transaction(function () use ($user, $comment, $on) {
            $exists = DB::table('clip_comment_likes')->where('clip_comment_id', $comment->id)->where('user_id', $user->id)->lockForUpdate()->exists();
            if ($on && ! $exists) {
                DB::table('clip_comment_likes')->insert(['clip_comment_id' => $comment->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
                ClipComment::whereKey($comment->id)->increment('likes_count');
            } elseif (! $on && $exists) {
                DB::table('clip_comment_likes')->where('clip_comment_id', $comment->id)->where('user_id', $user->id)->delete();
                $this->decrement(ClipComment::whereKey($comment->id), 'likes_count');
            }
        });

        return ['liked' => $on, 'likes_count' => (int) ClipComment::whereKey($comment->id)->value('likes_count')];
    }

    private function ensureCommentOf(EpisodeClip $clip, ClipComment $comment): void
    {
        if ((int) $comment->episode_clip_id !== (int) $clip->id) {
            abort(404, 'That item could not be found. It may have been removed.');
        }
    }

    private function decrement($query, string $column, int $by = 1): void
    {
        $query->update([$column => DB::raw("CASE WHEN {$column} > {$by} THEN {$column} - {$by} ELSE 0 END")]);
    }

    /**
     * The comment fragment, with what the viewer liked and who on the page teaches the course.
     *
     * @param  Collection<int, ClipComment>  $comments
     */
    private function presenter(User $user, EpisodeClip $clip, Collection $comments): \Closure
    {
        $ids = $comments->pluck('id');
        $liked = $ids->isEmpty() ? collect() : DB::table('clip_comment_likes')->where('user_id', $user->id)->whereIn('clip_comment_id', $ids)->pluck('clip_comment_id')->flip();
        $lecturers = $this->catalog->courseLecturerIds((int) $clip->course_id)->flip();
        $viewerTeaches = $lecturers->has($user->id);

        return fn (ClipComment $comment) => [
            'id' => $comment->id,
            'body' => $comment->body,
            'author' => [
                'id' => $comment->user_id,
                'name' => $comment->author?->name ?? 'Former member',
                'initials' => self::initials($comment->author?->name),
                'avatar_url' => $comment->author?->avatar_url,
                'is_lecturer' => $lecturers->has((int) $comment->user_id),
            ],
            'created_at' => $comment->created_at?->toIso8601String(),
            'likes_count' => (int) $comment->likes_count,
            'liked' => $liked->has($comment->id),
            'replies_count' => $comment->parent_id === null ? (int) ($comment->replies_count ?? 0) : 0,
            'can_delete' => (int) $comment->user_id === (int) $user->id || $viewerTeaches,
            'reply_to' => $comment->reply_to_user_id ? ['id' => $comment->reply_to_user_id, 'name' => $comment->replyTo?->name] : null,
        ];
    }

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(fn ($word) => mb_substr($word, 0, 1), array_slice($words, 0, 2)))) ?: '?';
    }
}
