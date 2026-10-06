<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCheck;
use App\Services\Episodes\WatchCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchController extends Controller
{
    public function __construct(private readonly WatchCatalog $catalog) {}

    public function home(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->catalog->home($request->user())]);
    }

    public function series(Request $request, CourseSeries $series): JsonResponse
    {
        return response()->json(['data' => $this->catalog->series($request->user(), $series)]);
    }

    public function show(Request $request, Episode $episode): JsonResponse
    {
        return response()->json(['data' => $this->catalog->playback($request->user(), $episode)]);
    }

    public function answer(Request $request, EpisodeCheck $check): JsonResponse
    {
        $episode = $check->episode;
        if (! $episode) {
            abort(404, 'That item could not be found. It may have been removed.');
        }
        $this->catalog->authorizeEpisode($request->user(), $episode);

        $request->validate(['option_id' => ['required', 'integer']]);

        return response()->json([
            'message' => 'Answer saved.',
            'data' => $this->catalog->answer($request->user(), $check, $request->integer('option_id')),
        ]);
    }

    public function progress(Request $request, Episode $episode): JsonResponse
    {
        $this->catalog->authorizeEpisode($request->user(), $episode);

        return response()->json([
            'message' => 'Progress saved.',
            'data' => $this->catalog->saveProgress($request->user(), $episode, $request->validate(WatchCatalog::PROGRESS_RULES)),
        ]);
    }
}
