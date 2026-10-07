<?php

declare(strict_types=1);

namespace App\Services\Episodes;

use App\Models\Episode;
use App\Models\EpisodeCheck;
use App\Models\EpisodeCheckAnswer;
use App\Models\EpisodeProgress;
use App\Models\EpisodeRewind;
use App\Models\EpisodeScene;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How a group of students watched an episode. Every figure is limited to the
 * given student ids, so a section lecturer only sees their own students.
 */
final class EpisodeAnalytics
{
    public const REMINDER_COOLDOWN_MINUTES = 60;

    /**
     * Active students of the given sections.
     *
     * @return Collection<int, int>
     */
    public static function studentIds(iterable $sectionIds): Collection
    {
        return SectionStudent::whereIn('section_id', collect($sectionIds)->all())
            ->where('is_active', true)
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * The sections a lecturer can filter by, each with its active student count.
     *
     * @param  Collection<int, Section>  $sections
     * @return list<array{id: int, name: string, students: int}>
     */
    public static function sectionOptions(Collection $sections): array
    {
        $counts = SectionStudent::whereIn('section_id', $sections->pluck('id'))
            ->where('is_active', true)
            ->selectRaw('section_id, count(*) as total')
            ->groupBy('section_id')
            ->pluck('total', 'section_id');

        return $sections
            ->map(fn (Section $section) => [
                'id' => $section->id,
                'name' => $section->name,
                'students' => (int) ($counts[$section->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Each active student's section name, for labelling rows when every section is shown.
     *
     * @param  Collection<int, Section>  $sections
     * @return array<int, string>
     */
    public static function sectionNamesByStudent(Collection $sections): array
    {
        $names = $sections->pluck('name', 'id');

        return SectionStudent::whereIn('section_id', $sections->pluck('id'))
            ->where('is_active', true)
            ->get(['section_id', 'user_id'])
            ->sortBy(fn (SectionStudent $row) => $names[$row->section_id])
            ->unique('user_id')
            ->mapWithKeys(fn (SectionStudent $row) => [$row->user_id => $names[$row->section_id]])
            ->all();
    }

    /**
     * Progress rows of these students, keyed by user id.
     *
     * @return Collection<int, EpisodeProgress>
     */
    public static function progressOf(Episode $episode, Collection $studentIds): Collection
    {
        return EpisodeProgress::where('episode_id', $episode->id)
            ->whereIn('user_id', $studentIds)
            ->get()
            ->keyBy('user_id');
    }

    public static function status(?EpisodeProgress $progress): string
    {
        return match (true) {
            $progress === null => 'not_started',
            $progress->completed_at !== null => 'finished',
            default => 'watching',
        };
    }

    public static function watchedPercent(?EpisodeProgress $progress, ?int $duration): int
    {
        if (! $progress) {
            return 0;
        }
        if ($progress->completed_at) {
            return 100;
        }

        return $duration ? (int) min(100, round($progress->furthest_seconds / $duration * 100)) : 0;
    }

    /**
     * Share of first answers that were right across the episode's checks, or null when none.
     */
    public static function firstTryPercent(Episode $episode, Collection $studentIds): ?int
    {
        $answers = EpisodeCheckAnswer::whereIn('episode_check_id', EpisodeCheck::where('episode_id', $episode->id)->select('id'))
            ->whereIn('user_id', $studentIds)
            ->get(['first_is_correct']);

        return $answers->isEmpty() ? null : (int) round($answers->where('first_is_correct', true)->count() / $answers->count() * 100);
    }

    public function report(Episode $episode, Collection $studentIds, array $idNumbers = [], array $sectionNames = []): array
    {
        $episode->loadMissing(['scenes', 'checks.options']);
        $duration = $episode->duration_seconds;
        $progress = self::progressOf($episode, $studentIds);
        $started = $progress->count();

        $users = User::whereIn('id', $studentIds)->get(['id', 'name'])->keyBy('id');
        $answers = EpisodeCheckAnswer::whereIn('episode_check_id', $episode->checks->pluck('id'))
            ->whereIn('user_id', $studentIds)
            ->get();
        $rewinds = EpisodeRewind::where('episode_id', $episode->id)
            ->whereIn('user_id', $studentIds)
            ->pluck('to_seconds');

        return [
            'audience' => [
                'students' => $studentIds->count(),
                'started' => $started,
                'finished' => $progress->whereNotNull('completed_at')->count(),
                'not_started' => $studentIds->count() - $started,
                'average_watched_percent' => $started
                    ? (int) round($progress->avg(fn (EpisodeProgress $p) => self::watchedPercent($p, $duration)))
                    : 0,
            ],
            ...$this->scenes($episode->scenes, $progress, $rewinds, $started),
            'checks' => $episode->checks->map(fn (EpisodeCheck $check) => $this->check($check, $answers->where('episode_check_id', $check->id)))->values()->all(),
            'students' => $studentIds
                ->map(function (int $id) use ($progress, $users, $answers, $duration, $idNumbers, $sectionNames) {
                    $row = $progress->get($id);

                    return [
                        'user_id' => $id,
                        'name' => $users->get($id)?->name,
                        'student_id_number' => $idNumbers[$id] ?? null,
                        'section_name' => $sectionNames[$id] ?? null,
                        'status' => self::status($row),
                        'watched_percent' => self::watchedPercent($row, $duration),
                        'last_watched_at' => $row?->last_watched_at?->toIso8601String(),
                        'checks_correct' => $answers->where('user_id', $id)->where('is_correct', true)->count(),
                    ];
                })
                ->sortBy(fn (array $s) => [['not_started' => 0, 'watching' => 1, 'finished' => 2][$s['status']], mb_strtolower((string) $s['name'])])
                ->values()
                ->all(),
            'reminder' => [
                'last_sent_at' => $episode->last_reminded_at?->toIso8601String(),
                'available_at' => self::reminderAvailableAt($episode)?->toIso8601String(),
            ],
        ];
    }

    public static function reminderAvailableAt(Episode $episode): ?Carbon
    {
        $next = $episode->last_reminded_at?->copy()->addMinutes(self::REMINDER_COOLDOWN_MINUTES);

        return $next && $next->isFuture() ? $next : null;
    }

    /**
     * @param  Collection<int, EpisodeScene>  $scenes
     * @param  Collection<int, int>  $rewinds  Landing points (to_seconds) of recorded rewinds
     */
    private function scenes(Collection $scenes, Collection $progress, Collection $rewinds, int $started): array
    {
        $rows = $scenes->values()->map(function (EpisodeScene $scene, int $i) use ($scenes, $progress, $rewinds, $started) {
            $end = $scenes->get($i + 1)?->start_seconds;
            $reached = $progress->filter(fn (EpisodeProgress $p) => $p->completed_at !== null || $p->furthest_seconds >= $scene->start_seconds)->count();

            return [
                'code' => $scene->code,
                'title' => $scene->title,
                'start_seconds' => $scene->start_seconds,
                'reached' => $reached,
                'reached_percent' => $started ? (int) round($reached / $started * 100) : 0,
                'rewinds' => $rewinds->filter(fn (int $to) => $to >= $scene->start_seconds && ($end === null || $to < $end))->count(),
            ];
        });

        $top = $rows->sortByDesc('rewinds')->first();

        return [
            'scenes' => $rows->all(),
            'most_rewound_scene' => $top && $top['rewinds'] > 0 && $started > 0 ? [
                'code' => $top['code'],
                'title' => $top['title'],
                'rewinds_per_viewer' => round($top['rewinds'] / $started, 1),
            ] : null,
        ];
    }

    private function check(EpisodeCheck $check, Collection $answers): array
    {
        $answered = $answers->count();

        return [
            'id' => $check->id,
            'at_seconds' => $check->at_seconds,
            'prompt' => $check->prompt,
            'answered' => $answered,
            'first_try_correct_percent' => $answered ? (int) round($answers->where('first_is_correct', true)->count() / $answered * 100) : null,
            'options' => $check->options->map(function ($option) use ($answers, $answered) {
                $chosen = $answers->where('episode_check_option_id', $option->id)->count();

                return [
                    'id' => $option->id,
                    'label' => $option->label,
                    'is_correct' => $option->is_correct,
                    'chosen' => $chosen,
                    'chosen_percent' => $answered ? (int) round($chosen / $answered * 100) : 0,
                ];
            })->values()->all(),
        ];
    }
}
