<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCheck;
use App\Services\Episodes\WatchCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Student "Watch" on the web: the same catalogue, progress and Quick Checks as the app.
 */
class StudentWatchController extends Controller
{
    public function __construct(private readonly WatchCatalog $catalog) {}

    public function index(Request $request): View
    {
        return view('tenant.watch.index', [
            'tenant' => app('current_tenant'),
            'home' => $this->catalog->home($request->user()),
        ]);
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
