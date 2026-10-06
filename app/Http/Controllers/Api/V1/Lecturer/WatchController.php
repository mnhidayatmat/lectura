<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Episode;
use App\Models\EpisodeProgress;
use App\Services\Episodes\EpisodeAnalytics;
use App\Services\Episodes\EpisodeMedia;
use App\Services\Episodes\EpisodeReminderSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use RuntimeException;

class WatchController extends Controller
{
    use AuthorizesLecturerAccess;

    public function course(Course $course): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        $series = $course->series;
        $studentIds = EpisodeAnalytics::studentIds($this->lecturerSectionIds($course));
        $episodes = $series ? $series->episodes()->withCount('checks')->get() : collect();

        $progress = EpisodeProgress::whereIn('episode_id', $episodes->pluck('id'))
            ->whereIn('user_id', $studentIds)
            ->get()
            ->groupBy('episode_id');

        return response()->json([
            'data' => [
                'series' => $series ? [
                    'id' => $series->id,
                    'title' => $series->title,
                    'tagline' => $series->tagline,
                    'cover_url' => EpisodeMedia::coverUrl($series),
                ] : null,
                'students_count' => $studentIds->count(),
                'episodes' => $episodes->map(fn (Episode $episode) => [
                    ...$this->episodeSummary($episode),
                    'started' => $progress->get($episode->id, collect())->count(),
                    'finished' => $progress->get($episode->id, collect())->whereNotNull('completed_at')->count(),
                    'checks_count' => (int) $episode->checks_count,
                    'first_try_correct_percent' => $episode->checks_count ? EpisodeAnalytics::firstTryPercent($episode, $studentIds) : null,
                ])->values(),
            ],
        ]);
    }

    public function show(Episode $episode, EpisodeAnalytics $analytics): JsonResponse
    {
        $studentIds = $this->authorizeEpisode($episode);

        return response()->json([
            'data' => [
                'episode' => [
                    ...$this->episodeSummary($episode),
                    'started' => EpisodeAnalytics::progressOf($episode, $studentIds)->count(),
                    'finished' => EpisodeAnalytics::progressOf($episode, $studentIds)->whereNotNull('completed_at')->count(),
                    'checks_count' => $episode->checks()->count(),
                    'first_try_correct_percent' => EpisodeAnalytics::firstTryPercent($episode, $studentIds),
                ],
                ...$analytics->report($episode, $studentIds, $this->studentIdNumbers($studentIds)->all()),
            ],
        ]);
    }

    public function remind(Request $request, Episode $episode, EpisodeReminderSender $sender): JsonResponse
    {
        $studentIds = $this->authorizeEpisode($episode);

        $audience = $request->validate([
            'audience' => ['nullable', Rule::in(['not_started', 'not_finished'])],
        ])['audience'] ?? 'not_started';

        try {
            $sent = $sender->send($episode, $studentIds, $audience);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'message' => "Reminder sent to {$sent} ".str('student')->plural($sent).'.',
            'data' => [
                'sent' => $sent,
                'last_sent_at' => $episode->last_reminded_at?->toIso8601String(),
                'available_at' => EpisodeAnalytics::reminderAvailableAt($episode)?->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return Collection<int, int> the lecturer's students in this episode's course
     */
    private function authorizeEpisode(Episode $episode): Collection
    {
        $this->ensureLecturer();
        $episode->loadMissing('course');
        $this->authorizeCourse($episode->course);

        return EpisodeAnalytics::studentIds($this->lecturerSectionIds($episode->course));
    }

    private function episodeSummary(Episode $episode): array
    {
        return [
            'id' => $episode->id,
            'episode_number' => $episode->episode_number,
            'title' => $episode->title,
            'source' => $episode->source ?? Episode::SOURCE_UPLOAD,
            'week_number' => $episode->week_number,
            'status' => $episode->status,
            'is_available' => $episode->isAvailable(),
            'available_at' => $episode->availableAt()?->toIso8601String(),
            'required_by' => $episode->required_by?->toIso8601String(),
            'duration_seconds' => $episode->duration_seconds,
            'poster_url' => EpisodeMedia::posterUrl($episode),
        ];
    }
}
