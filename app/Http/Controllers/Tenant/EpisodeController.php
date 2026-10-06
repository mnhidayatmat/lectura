<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\CourseTopic;
use App\Models\Episode;
use App\Services\Episodes\EpisodeAnnouncer;
use App\Services\Episodes\EpisodeMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lecturer side of "Watch": one animated series per course, one episode per video.
 */
class EpisodeController extends Controller
{
    use AuthorizesCourseAccess;

    public function index(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        $tenant = app('current_tenant');
        $series = $course->series;
        $episodes = $series
            ? $series->episodes()->with('topic')->withCount(['scenes', 'checks', 'captions', 'progress', 'progress as completed_count' => fn ($q) => $q->whereNotNull('completed_at')])->get()
            : collect();
        $topics = $course->topics()->orderBy('week_number')->orderBy('sort_order')->get();
        $nextNumber = ((int) $episodes->max('episode_number')) + 1;
        $maxVideoMb = $this->maxVideoMb();

        return view('tenant.episodes.index', compact('tenant', 'course', 'series', 'episodes', 'topics', 'nextNumber', 'maxVideoMb'));
    }

    public function saveSeries(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover' => ['nullable', 'image', 'max:5120'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);

        $series = $course->series ?? new CourseSeries(['course_id' => $course->id, 'tenant_id' => $course->tenant_id]);
        $series->fill([
            'title' => $validated['title'],
            'tagline' => $validated['tagline'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        if ($request->hasFile('cover') || $request->boolean('remove_cover')) {
            $this->deleteMedia(config('lectura.episodes.disk'), $series->cover_path);
            $series->cover_path = $request->hasFile('cover')
                ? $this->storeMedia($request->file('cover'), "episodes/{$course->id}/covers")
                : null;
        }

        $series->save();

        return back()->with('success', 'Series saved.');
    }

    public function store(Request $request, string $tenantSlug, Course $course, EpisodeAnnouncer $announcer): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $validated = $request->validate([
            ...$this->episodeRules($course),
            'video' => ['required', ...$this->videoRules()],
        ], $this->messages());

        $series = $course->series ?? CourseSeries::create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'title' => $course->title,
        ]);

        $disk = config('lectura.episodes.disk');
        $video = $request->file('video');

        $episode = new Episode([
            'tenant_id' => $course->tenant_id,
            'course_series_id' => $series->id,
            'course_id' => $course->id,
            'uploaded_by' => $request->user()->id,
            'video_disk' => $disk,
            'video_path' => $this->storeMedia($video, "episodes/{$course->id}"),
            'video_mime' => $video->getMimeType() ?: 'video/mp4',
            'video_size_bytes' => $video->getSize(),
        ]);
        $this->fillEpisode($episode, $validated, $course, $series);

        if ($request->hasFile('poster')) {
            $episode->poster_path = $this->storeMedia($request->file('poster'), "episodes/{$course->id}/posters");
        }

        $episode->save();
        $announced = $announcer->announceIfDue($episode);

        return back()->with('success', "Episode {$episode->episode_number} uploaded.".($announced ? ' Students were notified.' : ''));
    }

    public function update(Request $request, string $tenantSlug, Course $course, Episode $episode, EpisodeAnnouncer $announcer): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        $this->ensureEpisodeOfCourse($course, $episode);

        $validated = $request->validate([
            ...$this->episodeRules($course),
            'video' => ['nullable', ...$this->videoRules()],
            'remove_poster' => ['nullable', 'boolean'],
        ], $this->messages());

        $this->fillEpisode($episode, $validated, $course, $episode->series);

        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $this->deleteMedia($episode->video_disk, $episode->video_path);
            $episode->video_disk = config('lectura.episodes.disk');
            $episode->video_path = $this->storeMedia($video, "episodes/{$course->id}");
            $episode->video_mime = $video->getMimeType() ?: 'video/mp4';
            $episode->video_size_bytes = $video->getSize();
            // The browser re-reads the new file's length into duration_seconds; never keep the old one.
            $episode->duration_seconds = isset($validated['duration_seconds']) ? (int) $validated['duration_seconds'] : null;
        }

        if ($request->hasFile('poster') || $request->boolean('remove_poster')) {
            $this->deleteMedia($episode->video_disk, $episode->poster_path);
            $episode->poster_path = $request->hasFile('poster')
                ? $this->storeMedia($request->file('poster'), "episodes/{$course->id}/posters")
                : null;
        }

        $episode->save();
        $announced = $announcer->announceIfDue($episode);

        return back()->with('success', "Episode {$episode->episode_number} saved.".($announced ? ' Students were notified.' : ''));
    }

    public function destroy(string $tenantSlug, Course $course, Episode $episode): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        $this->ensureEpisodeOfCourse($course, $episode);

        $this->deleteMedia($episode->video_disk, $episode->video_path);
        $this->deleteMedia($episode->video_disk, $episode->poster_path);
        foreach ($episode->captions as $caption) {
            $this->deleteMedia($caption->disk, $caption->path);
        }
        $episode->delete();

        return back()->with('success', 'Episode deleted.');
    }

    private function episodeRules(Course $course): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'episode_number' => ['required', 'integer', 'min:1', 'max:999'],
            'course_topic_id' => ['nullable', 'integer', Rule::exists('course_topics', 'id')->where('course_id', $course->id)],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:52'],
            'synopsis' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Episode::STATUS_DRAFT, Episode::STATUS_PUBLISHED])],
            'publish_at' => ['nullable', 'date'],
            'required_by' => ['nullable', 'date'],
            'notify_students' => ['nullable', 'boolean'],
            'allow_download' => ['nullable', 'boolean'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'poster' => ['nullable', 'image', 'max:5120'],
        ];
    }

    private function videoRules(): array
    {
        return [
            'file',
            'mimetypes:video/mp4,video/quicktime,video/x-m4v',
            'max:'.($this->maxVideoMb() * 1024),
        ];
    }

    private function messages(): array
    {
        $mb = $this->maxVideoMb();

        return [
            'video.mimetypes' => 'Upload the episode as an MP4 (H.264) video.',
            'video.max' => "The video must be {$mb} MB or smaller.",
            'video.uploaded' => "The video did not finish uploading. Files must be {$mb} MB or smaller.",
        ];
    }

    /**
     * The configured ceiling, or PHP's own upload limit when that is lower, so the
     * page never promises a size the server would drop before validation runs.
     */
    private function maxVideoMb(): int
    {
        $phpLimitMb = (int) floor(UploadedFile::getMaxFilesize() / 1048576);
        $configured = (int) config('lectura.episodes.max_video_mb');

        return $phpLimitMb > 0 ? min($configured, $phpLimitMb) : $configured;
    }

    /**
     * A topic fixes the week; a week alone links the course's topic for that week when there is one.
     */
    private function fillEpisode(Episode $episode, array $validated, Course $course, CourseSeries $series): void
    {
        $topic = ! empty($validated['course_topic_id'])
            ? CourseTopic::find($validated['course_topic_id'])
            : null;
        $week = $topic?->week_number ?? ($validated['week_number'] ?? null);
        $topic ??= $week ? $course->topics()->where('week_number', $week)->orderBy('sort_order')->first() : null;

        $tz = $course->tenant?->timezone ?: config('app.timezone');
        $local = fn (?string $value) => ! empty($value) ? Carbon::parse($value, $tz)->utc() : null;

        $episode->fill([
            'course_series_id' => $series->id,
            'title' => $validated['title'],
            'episode_number' => (int) $validated['episode_number'],
            'week_number' => $week ? (int) $week : null,
            'course_topic_id' => $topic?->id,
            'synopsis' => $validated['synopsis'] ?? null,
            'status' => $validated['status'],
            // The form's datetime-local values are the institution's wall-clock time; store UTC.
            'publish_at' => $local($validated['publish_at'] ?? null),
            'required_by' => $local($validated['required_by'] ?? null),
            'notify_students' => (bool) ($validated['notify_students'] ?? false),
            'allow_download' => (bool) ($validated['allow_download'] ?? true),
        ]);

        if (! empty($validated['duration_seconds'])) {
            $episode->duration_seconds = (int) $validated['duration_seconds'];
        }
    }

    private function ensureEpisodeOfCourse(Course $course, Episode $episode): void
    {
        if ((int) $episode->course_id !== $course->id) {
            abort(404);
        }
    }

    private function storeMedia(UploadedFile $file, string $directory): string
    {
        $path = EpisodeMedia::disk()->putFile($directory, $file);

        if ($path === false) {
            abort(500, 'The file could not be stored. Try again.');
        }

        return $path;
    }

    private function deleteMedia(?string $disk, ?string $path): void
    {
        if ($disk && $path) {
            EpisodeMedia::disk($disk)->delete($path);
        }
    }
}
