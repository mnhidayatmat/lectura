<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\Student\Concerns\InteractsWithEnrollments;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\WatchPresenter;
use App\Models\AttendanceRecord;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCaption;
use App\Models\EpisodeCheck;
use App\Models\EpisodeCheckAnswer;
use App\Models\EpisodeCheckOption;
use App\Models\EpisodeProgress;
use App\Models\EpisodeRewind;
use App\Models\EpisodeScene;
use App\Models\Section;
use App\Models\User;
use App\Services\Episodes\EpisodeMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WatchController extends Controller
{
    use InteractsWithEnrollments;

    public function home(Request $request): JsonResponse
    {
        $user = $request->user();

        $seriesList = CourseSeries::whereIn('course_id', $this->enrolledCourseIds($user))
            ->whereHas('episodes', fn ($q) => $q->published())
            ->with(['course.lecturer', 'course.academicTerm', 'publishedEpisodes.topic'])
            ->get()
            ->sortBy(fn (CourseSeries $series) => $series->course?->code)
            ->values();

        $episodes = $seriesList->flatMap(fn (CourseSeries $series) => $this->attachCourse($series));
        $watch = $this->presenter($user, $episodes);

        $available = $episodes->filter(fn (Episode $e) => $e->isAvailable());
        $byRelease = $available->sortByDesc(fn (Episode $e) => $e->availableAt()->getTimestamp())->values();
        $unfinished = $byRelease->reject(fn (Episode $e) => $watch->isCompleted($e));

        $featured = $unfinished
            ->filter(fn (Episode $e) => $e->required_by?->isFuture())
            ->sortBy(fn (Episode $e) => $e->required_by->getTimestamp())
            ->first()
            ?? $available
                ->reject(fn (Episode $e) => $watch->isCompleted($e))
                ->first(fn (Episode $e) => $e->week_number !== null && $e->week_number === $e->course->currentWeek())
            ?? $unfinished->first()
            ?? $byRelease->first();

        $continue = $available
            ->filter(fn (Episode $e) => $watch->progressOf($e) !== null && ! $watch->isCompleted($e))
            ->sortByDesc(fn (Episode $e) => $watch->progressOf($e)->last_watched_at?->getTimestamp() ?? 0)
            ->take(10);

        $new = $byRelease->filter(fn (Episode $e) => $watch->isNew($e))->take(10);
        $missed = $this->missedEpisodes($user, $available, $watch);

        return response()->json([
            'data' => [
                'featured' => $featured ? [
                    ...$watch->episode($featured),
                    'series_title' => $seriesList->firstWhere('id', $featured->course_series_id)?->title,
                ] : null,
                'continue_watching' => $continue->map(fn (Episode $e) => $watch->episode($e))->values(),
                'new_episodes' => $new->map(fn (Episode $e) => $watch->episode($e))->values(),
                'because_you_missed' => $missed->map(fn (array $row) => [
                    'week_number' => $row['week_number'],
                    'missed_on' => $row['missed_on']?->toIso8601String(),
                    'episode' => $watch->episode($row['episode']),
                ])->values(),
                'series' => $seriesList->map(fn (CourseSeries $series) => [
                    ...$watch->series($series, $series->publishedEpisodes),
                    'episodes' => $series->publishedEpisodes->map(fn (Episode $e) => $watch->episode($e))->values(),
                ])->values(),
            ],
        ]);
    }

    public function series(Request $request, CourseSeries $series): JsonResponse
    {
        $series->loadMissing(['course.lecturer', 'course.academicTerm', 'course.learningOutcomes', 'publishedEpisodes.topic']);

        if ($series->publishedEpisodes->isEmpty()) {
            abort(404, 'That item could not be found. It may have been removed.');
        }

        $this->ensureEnrolled($series->course, $request->user());

        $episodes = $this->attachCourse($series);
        $watch = $this->presenter($request->user(), $episodes);

        $upNext = $episodes->first(fn (Episode $e) => $e->isAvailable() && ! $watch->isCompleted($e));

        return response()->json([
            'data' => [
                ...$watch->series($series, $episodes),
                'learning_outcomes' => $series->course->learningOutcomes
                    ->sortBy('sort_order')
                    ->map(fn ($clo) => ['code' => $clo->code, 'description' => $clo->description])
                    ->values(),
                'up_next' => $upNext ? $watch->episode($upNext) : null,
                'episodes' => $episodes->map(fn (Episode $e) => $watch->episode($e))->values(),
            ],
        ]);
    }

    public function show(Request $request, Episode $episode): JsonResponse
    {
        $this->authorizeEpisode($request, $episode);

        $series = $episode->series()->with(['publishedEpisodes.topic'])->firstOrFail();
        $episodes = $this->attachCourse($series, $episode->course);
        $watch = $this->presenter($request->user(), $episodes);

        $episode->load(['scenes', 'checks.options', 'captions']);
        $myAnswers = EpisodeCheckAnswer::where('user_id', $request->user()->id)
            ->whereIn('episode_check_id', $episode->checks->pluck('id'))
            ->get()
            ->keyBy('episode_check_id');

        $index = $episodes->search(fn (Episode $e) => $e->id === $episode->id);
        $next = $index === false ? null : $episodes->get($index + 1);
        $expires = EpisodeMedia::expiresAt();

        return response()->json([
            'data' => [
                ...$watch->episode($episode),
                'series' => ['id' => $series->id, 'title' => $series->title],
                'stream_url' => EpisodeMedia::streamUrl($episode, $expires),
                'stream_expires_at' => $expires->toIso8601String(),
                'mime_type' => $episode->video_mime,
                'size_bytes' => $episode->video_size_bytes,
                'can_download' => (bool) $episode->allow_download,
                'next_episode' => $next ? $watch->episode($next) : null,
                'scenes' => $episode->scenes->map(fn (EpisodeScene $scene) => [
                    'code' => $scene->code,
                    'title' => $scene->title,
                    'start_seconds' => $scene->start_seconds,
                ])->values(),
                'checks' => $episode->checks->map(function (EpisodeCheck $check) use ($myAnswers) {
                    $answer = $myAnswers->get($check->id);

                    return [
                        'id' => $check->id,
                        'at_seconds' => $check->at_seconds,
                        'prompt' => $check->prompt,
                        'options' => $check->options->map(fn (EpisodeCheckOption $o) => ['id' => $o->id, 'label' => $o->label])->values(),
                        'my_answer' => $answer ? [
                            'option_id' => $answer->episode_check_option_id,
                            'is_correct' => $answer->is_correct,
                            'attempts' => $answer->attempts,
                            'answered_at' => $answer->answered_at?->toIso8601String(),
                        ] : null,
                    ];
                })->values(),
                'captions' => $episode->captions->map(fn (EpisodeCaption $caption) => [
                    'language' => $caption->language,
                    'label' => $caption->label(),
                    'url' => EpisodeMedia::captionUrl($caption, $expires),
                ])->values(),
            ],
        ]);
    }

    public function answer(Request $request, EpisodeCheck $check): JsonResponse
    {
        $episode = $check->episode;
        if (! $episode) {
            abort(404, 'That item could not be found. It may have been removed.');
        }
        $this->authorizeEpisode($request, $episode);

        $request->validate(['option_id' => ['required', 'integer']]);
        $option = $check->options()->whereKey($request->integer('option_id'))->first();
        if (! $option) {
            throw ValidationException::withMessages(['option_id' => 'Choose one of this question\'s options.']);
        }

        $answer = EpisodeCheckAnswer::firstOrNew([
            'episode_check_id' => $check->id,
            'user_id' => $request->user()->id,
        ]);
        $isFirst = ! $answer->exists;
        $answer->fill([
            'episode_check_option_id' => $option->id,
            'is_correct' => $option->is_correct,
            'attempts' => $isFirst ? 1 : $answer->attempts + 1,
            'answered_at' => now(),
        ]);
        if ($isFirst) {
            $answer->first_is_correct = $option->is_correct;
        }
        $answer->save();

        return response()->json([
            'message' => 'Answer saved.',
            'data' => [
                'is_correct' => $answer->is_correct,
                'correct_option_id' => $check->options()->where('is_correct', true)->value('id'),
                'explanation' => $check->explanation,
                'attempts' => $answer->attempts,
            ],
        ]);
    }

    public function progress(Request $request, Episode $episode): JsonResponse
    {
        $this->authorizeEpisode($request, $episode);

        $validated = $request->validate([
            'position_seconds' => ['required', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'completed' => ['nullable', 'boolean'],
            'rewinds' => ['nullable', 'array', 'max:50'],
            'rewinds.*.from_seconds' => ['required', 'integer', 'min:0'],
            'rewinds.*.to_seconds' => ['required', 'integer', 'min:0'],
            'watched_at' => ['nullable', 'date'],
        ]);

        if (! $episode->duration_seconds && ! empty($validated['duration_seconds'])) {
            $episode->update(['duration_seconds' => (int) $validated['duration_seconds']]);
        }

        $duration = $episode->duration_seconds;
        $position = (int) $validated['position_seconds'];
        if ($duration) {
            $position = min($position, $duration);
        }

        $progress = EpisodeProgress::firstOrNew([
            'episode_id' => $episode->id,
            'user_id' => $request->user()->id,
        ]);

        $reachedEnd = $duration && $position >= $duration * (float) config('lectura.episodes.complete_ratio');

        // A save queued offline must not drag the position back behind a newer one.
        $watchedAt = isset($validated['watched_at']) ? min(Carbon::parse($validated['watched_at']), now()) : now();
        $isStale = $progress->last_watched_at !== null && $watchedAt->lt($progress->last_watched_at);

        if (! $isStale) {
            $progress->position_seconds = $position;
            $progress->last_watched_at = $watchedAt;
        }
        $progress->furthest_seconds = max((int) $progress->furthest_seconds, $position);
        if (! $progress->completed_at && (($validated['completed'] ?? false) || $reachedEnd)) {
            $progress->completed_at = now();
        }
        $progress->save();

        $rewinds = collect($validated['rewinds'] ?? [])
            ->filter(fn (array $r) => $r['from_seconds'] - $r['to_seconds'] >= 5)
            ->map(fn (array $r) => [
                'episode_id' => $episode->id,
                'user_id' => $request->user()->id,
                'from_seconds' => $duration ? min((int) $r['from_seconds'], $duration) : (int) $r['from_seconds'],
                'to_seconds' => (int) $r['to_seconds'],
                'created_at' => now(),
            ]);
        if ($rewinds->isNotEmpty()) {
            EpisodeRewind::insert($rewinds->values()->all());
        }

        return response()->json([
            'message' => 'Progress saved.',
            'data' => WatchPresenter::progress($progress, $duration),
        ]);
    }

    /**
     * Drafts are invisible (404); scheduled episodes are visible but locked (403).
     */
    private function authorizeEpisode(Request $request, Episode $episode): void
    {
        if (! $episode->isPublished()) {
            abort(404, 'That item could not be found. It may have been removed.');
        }

        $episode->loadMissing(['course.academicTerm', 'topic']);
        $this->ensureEnrolled($episode->course, $request->user());

        if (! $episode->isAvailable()) {
            abort(403, 'This episode is not available yet.');
        }
    }

    /**
     * The series' published episodes, each pointing at the already loaded course.
     *
     * @return Collection<int, Episode>
     */
    private function attachCourse(CourseSeries $series, $course = null): Collection
    {
        $course ??= $series->course;

        return $series->publishedEpisodes->each(fn (Episode $e) => $e->setRelation('course', $course))->values();
    }

    /**
     * Available, unfinished episodes for the weeks the student was marked absent
     * (excused absences don't count), most recent absence first.
     *
     * @param  Collection<int, Episode>  $available
     * @return Collection<int, array{week_number: int, missed_on: ?Carbon, episode: Episode}>
     */
    private function missedEpisodes(User $user, Collection $available, WatchPresenter $watch): Collection
    {
        if ($available->isEmpty()) {
            return collect();
        }

        $sectionCourse = Section::whereIn('id', $this->enrolledSectionIds($user))->pluck('course_id', 'id');

        $absences = AttendanceRecord::where('user_id', $user->id)
            ->where('status', 'absent')
            ->whereHas('session', fn ($q) => $q->whereIn('section_id', $sectionCourse->keys())->whereNotNull('week_number'))
            ->with('session')
            ->get()
            ->sortByDesc(fn (AttendanceRecord $r) => $r->session->started_at?->getTimestamp() ?? 0);

        $rows = collect();
        foreach ($absences as $record) {
            $courseId = $sectionCourse->get($record->session->section_id);
            $week = (int) $record->session->week_number;

            $available
                ->filter(fn (Episode $e) => (int) $e->course_id === (int) $courseId && $e->week_number === $week && ! $watch->isCompleted($e))
                ->each(function (Episode $e) use ($rows, $week, $record) {
                    if (! $rows->has($e->id)) {
                        $rows->put($e->id, ['week_number' => $week, 'missed_on' => $record->session->started_at, 'episode' => $e]);
                    }
                });
        }

        return $rows->values()->take(10);
    }

    /**
     * @param  Collection<int, Episode>  $episodes
     */
    private function presenter(User $user, Collection $episodes): WatchPresenter
    {
        $ids = $episodes->pluck('id')->unique()->values();

        $checkCounts = $ids->isEmpty() ? collect() : EpisodeCheck::whereIn('episode_id', $ids)
            ->selectRaw('episode_id, count(*) as aggregate')
            ->groupBy('episode_id')
            ->pluck('aggregate', 'episode_id');

        $answers = $ids->isEmpty() ? collect() : EpisodeCheckAnswer::where('user_id', $user->id)
            ->whereHas('check', fn ($q) => $q->whereIn('episode_id', $ids))
            ->with('check:id,episode_id')
            ->get()
            ->groupBy(fn (EpisodeCheckAnswer $a) => $a->check->episode_id);

        return new WatchPresenter($this->progressFor($user, $episodes), $checkCounts, $answers);
    }

    /**
     * @param  Collection<int, Episode>  $episodes
     * @return Collection<int, EpisodeProgress>
     */
    private function progressFor(User $user, Collection $episodes): Collection
    {
        if ($episodes->isEmpty()) {
            return collect();
        }

        return EpisodeProgress::where('user_id', $user->id)
            ->whereIn('episode_id', $episodes->pluck('id'))
            ->get()
            ->keyBy('episode_id');
    }
}
