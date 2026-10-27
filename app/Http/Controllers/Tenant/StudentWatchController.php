<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCheck;
use App\Models\EpisodeClip;
use App\Services\Episodes\ClipInteractions;
use App\Services\Episodes\WatchCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Student "Watch" on the web: the same catalogue, progress and Quick Checks as the app.
 */
class StudentWatchController extends Controller
{
    public function __construct(
        private readonly WatchCatalog $catalog,
        private readonly ClipInteractions $interactions,
    ) {}

    public function index(Request $request): View
    {
        return view('tenant.watch.index', [
            'tenant' => app('current_tenant'),
            'home' => $this->catalog->home($request->user()),
            // New clips lead and watched ones trail, as in the feed.
            'clips' => array_merge(...array_values($this->catalog->arrangeClipFeed($this->catalog->clips($request->user())))),
        ]);
    }

    /**
     * The clip feed: every enrolled course's clips, or one course's (`?course=`), opened on
     * `?clip=` when given. New clips come first and watched ones are held back behind an
     * "all caught up" card; `?again=1` plays them after the rest, and `?episode=` narrows the
     * feed to one episode, watched clips included.
     */
    public function clips(Request $request): View
    {
        $courseId = $request->filled('course') ? $request->integer('course') : null;
        $all = $this->catalog->clips($request->user(), $courseId);
        $keepId = $request->filled('clip') ? $request->integer('clip') : null;

        $episodeId = $request->integer('episode') ?: null;
        $episodeClips = array_values(array_filter($all, fn (array $clip) => $clip['episode']['id'] === $episodeId));
        $feed = $this->catalog->arrangeClipFeed($all, $keepId);

        if ($episodeClips !== []) {
            [$clips, $caughtUp, $start] = [$episodeClips, false, 0];
        } elseif ($request->boolean('again')) {
            [$clips, $caughtUp, $start] = [[...$feed['up_next'], ...$feed['watched']], false, count($feed['up_next'])];
        } else {
            [$clips, $caughtUp, $start] = [$feed['up_next'], true, 0];
        }
        $found = collect($clips)->search(fn (array $clip) => $clip['id'] === $keepId);

        $episodes = collect($all)->groupBy(fn (array $clip) => $clip['episode']['id'])->map(fn ($group) => [
            'id' => $group[0]['episode']['id'],
            'number' => $group[0]['episode']['episode_number'],
            'title' => $group[0]['episode']['title'],
            'course_code' => $group[0]['course']['code'],
            'count' => $group->count(),
            'new' => $group->contains(fn (array $clip) => $clip['is_new']),
            'watched' => $group->every(fn (array $clip) => $clip['watched']),
        ])->values()->all();

        return view('tenant.watch.clips', [
            'tenant' => app('current_tenant'),
            'hasClips' => $all !== [],
            'clips' => $clips,
            'start' => $found === false ? min($start, max(count($clips) - 1, 0)) : $found,
            'caughtUp' => $caughtUp,
            'canWatchAgain' => $feed['watched'] !== [],
            'episodes' => $episodes,
            'episode' => $episodeClips === [] ? null : collect($episodes)->firstWhere('id', $episodeId),
            'courseId' => $courseId,
            'multiCourse' => collect($all)->pluck('course.id')->unique()->count() > 1,
        ]);
    }

    /** The student played a clip to 90% in the web feed; it is passed over from then on. */
    public function clipWatched(Request $request, string $tenantSlug, EpisodeClip $clip): JsonResponse
    {
        $this->catalog->authorizeClip($request->user(), $clip);
        $this->interactions->markWatched($request->user(), $clip);

        return response()->json(['message' => 'Clip watched.', 'data' => ['watched' => true]]);
    }

    public function series(Request $request, string $tenantSlug, CourseSeries $series): View
    {
        return view('tenant.watch.series', [
            'tenant' => app('current_tenant'),
            'series' => $this->catalog->series($request->user(), $series),
        ]);
    }

    public function episode(Request $request, string $tenantSlug, Episode $episode): View
    {
        return view('tenant.watch.episode', [
            'tenant' => app('current_tenant'),
            'playback' => $this->catalog->playback($request->user(), $episode),
        ]);
    }

    public function progress(Request $request, string $tenantSlug, Episode $episode): JsonResponse
    {
        $this->catalog->authorizeEpisode($request->user(), $episode);

        return response()->json([
            'message' => 'Progress saved.',
            'data' => $this->catalog->saveProgress($request->user(), $episode, $request->validate(WatchCatalog::PROGRESS_RULES)),
        ]);
    }

    public function answer(Request $request, string $tenantSlug, EpisodeCheck $check): JsonResponse
    {
        $episode = $check->episode;
        if (! $episode) {
            abort(404);
        }
        $this->catalog->authorizeEpisode($request->user(), $episode);
        $request->validate(['option_id' => ['required', 'integer']]);

        return response()->json([
            'message' => 'Answer saved.',
            'data' => $this->catalog->answer($request->user(), $check, $request->integer('option_id')),
        ]);
    }
}
