<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Episode;
use App\Services\Episodes\WatchCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Preview as student": the student Watch pages for a lecturer, read-only. Drafts stay out of the
 * series page like they do for students, but any episode plays, and nothing is saved.
 */
class EpisodePreviewController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(private readonly WatchCatalog $catalog) {}

    public function series(Request $request, string $tenantSlug, Course $course): View|RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        $tenant = app('current_tenant');
        $series = $course->series;

        if (! $series || ! $series->publishedEpisodes()->exists()) {
            return redirect()->route('tenant.episodes.index', [$tenant->slug, $course])
                ->with('error', "Students can't see any episodes yet. Set one to Locked or Published to preview it.");
        }

        return view('tenant.watch.series', [
            'tenant' => $tenant,
            'series' => $this->catalog->series($request->user(), $series, preview: true),
            'preview' => [
                'backUrl' => route('tenant.episodes.index', [$tenant->slug, $course]),
                'episodeUrl' => fn (array $ep) => route('tenant.episodes.preview.episode', [$tenant->slug, $course, $ep['id']]),
            ],
        ]);
    }

    public function episode(Request $request, string $tenantSlug, Course $course, Episode $episode): View
    {
        $this->authorizeCourseAccess($course);
        if ((int) $episode->course_id !== $course->id) {
            abort(404);
        }
        $tenant = app('current_tenant');
        $playback = $this->catalog->playback($request->user(), $episode, preview: true);
        $next = $playback['next_episode'];

        return view('tenant.watch.episode', [
            'tenant' => $tenant,
            'playback' => $playback,
            'preview' => [
                'seriesUrl' => route('tenant.episodes.preview', [$tenant->slug, $course]),
                'nextUrl' => $next ? route('tenant.episodes.preview.episode', [$tenant->slug, $course, $next['id']]) : null,
                'manageUrl' => route('tenant.episodes.index', [$tenant->slug, $course]),
            ],
        ]);
    }
}
