<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCheckAnswer;
use App\Models\EpisodeProgress;
use App\Services\Episodes\EpisodeMedia;
use Illuminate\Support\Collection;

/**
 * JSON fragments for the student Watch endpoints (docs/mobile-api/student.md → Watch).
 */
final class WatchPresenter
{
    /**
     * @param  Collection<int, EpisodeProgress>  $progress  The student's progress, keyed by episode id
     * @param  Collection<int, int>  $checkCounts  Quick Checks per episode, keyed by episode id
     * @param  Collection<int, Collection<int, EpisodeCheckAnswer>>  $answers  The student's latest answers, grouped by episode id
     */
    public function __construct(
        private readonly Collection $progress,
        private readonly Collection $checkCounts = new Collection,
        private readonly Collection $answers = new Collection,
    ) {}

    public function progressOf(Episode $episode): ?EpisodeProgress
    {
        return $this->progress->get($episode->id);
    }

    public function isCompleted(Episode $episode): bool
    {
        return $this->progressOf($episode)?->completed_at !== null;
    }

    public function isNew(Episode $episode): bool
    {
        return $episode->isAvailable()
            && $this->progressOf($episode) === null
            && $episode->availableAt()->gte(now()->subDays((int) config('lectura.episodes.new_days')));
    }

    public function isOverdue(Episode $episode): bool
    {
        return $episode->required_by !== null && $episode->required_by->isPast() && ! $this->isCompleted($episode);
    }

    /**
     * @return array{total: int, answered: int, correct: int}
     */
    public function checkSummary(Episode $episode): array
    {
        $answers = $this->answers->get($episode->id, collect());

        return [
            'total' => (int) $this->checkCounts->get($episode->id, 0),
            'answered' => $answers->count(),
            'correct' => $answers->where('is_correct', true)->count(),
        ];
    }

    public function episode(Episode $episode): array
    {
        $topic = $episode->topic;

        return [
            'id' => $episode->id,
            'series_id' => $episode->course_series_id,
            'course' => StudentPresenter::course($episode->course),
            'episode_number' => $episode->episode_number,
            'title' => $episode->title,
            'synopsis' => $episode->synopsis,
            'week_number' => $episode->week_number,
            'topic' => $topic ? [
                'id' => $topic->id,
                'week_number' => (int) $topic->week_number,
                'title' => $topic->title,
            ] : null,
            'duration_seconds' => $episode->duration_seconds,
            'poster_url' => EpisodeMedia::posterUrl($episode),
            'is_available' => $episode->isAvailable(),
            'available_at' => $episode->availableAt()?->toIso8601String(),
            'is_new' => $this->isNew($episode),
            'required_by' => $episode->required_by?->toIso8601String(),
            'is_overdue' => $this->isOverdue($episode),
            'quick_checks' => $this->checkSummary($episode),
            'progress' => self::progress($this->progressOf($episode), $episode->duration_seconds),
        ];
    }

    public static function progress(?EpisodeProgress $progress, ?int $duration): ?array
    {
        if (! $progress) {
            return null;
        }

        $completed = $progress->completed_at !== null;
        $percent = $completed ? 100 : ($duration ? (int) min(100, round($progress->position_seconds / $duration * 100)) : 0);

        return [
            'position_seconds' => $progress->position_seconds,
            'watched_percent' => $percent,
            'completed' => $completed,
            'last_watched_at' => $progress->last_watched_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, Episode>  $published  The series' published episodes (scheduled ones included)
     */
    public function series(CourseSeries $series, Collection $published): array
    {
        $course = $series->course;

        return [
            'id' => $series->id,
            'course' => StudentPresenter::course($course),
            'title' => $series->title,
            'tagline' => $series->tagline,
            'description' => $series->description,
            'cover_url' => EpisodeMedia::coverUrl($series),
            'lecturer_name' => $course?->lecturer?->name,
            'current_week' => $course?->currentWeek(),
            'episodes_count' => $published->count(),
            'available_count' => $published->filter(fn (Episode $e) => $e->isAvailable())->count(),
            'completed_count' => $published->filter(fn (Episode $e) => $this->isCompleted($e))->count(),
        ];
    }
}
