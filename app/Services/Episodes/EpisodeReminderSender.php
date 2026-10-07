<?php

declare(strict_types=1);

namespace App\Services\Episodes;

use App\Models\Episode;
use App\Models\EpisodeSectionReminder;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\User;
use App\Notifications\EpisodeReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Lecturer-triggered nudge to the students who haven't started (or finished) an episode.
 * Each section may be reminded once an hour per episode; a section still inside its hour
 * is left out, so reminding Section 01 never holds back Section 02.
 */
final class EpisodeReminderSender
{
    /**
     * @param  Collection<int, int>  $sectionIds  The lecturer's sections to remind
     * @return array{sent: int, held: list<array{name: string, until: Carbon}>}
     *
     * @throws RuntimeException with a message to show the lecturer as is
     */
    public function send(Episode $episode, Collection $sectionIds, string $audience): array
    {
        if (! $episode->isAvailable()) {
            $this->fail('This episode is not released yet.');
        }

        $tz = $episode->tenant?->timezone ?: config('app.timezone');
        $cooldown = EpisodeAnalytics::REMINDER_COOLDOWN_MINUTES;
        $reminded = EpisodeAnalytics::remindedAt($episode, $sectionIds)
            ->filter(fn (Carbon $at) => $at->gt(now()->subMinutes($cooldown)));
        $free = $sectionIds->reject(fn (int $id) => $reminded->has($id))->values();

        if ($free->isEmpty()) {
            $last = $reminded->max();
            $this->fail(sprintf(
                'A reminder went out at %s. You can send another after %s.',
                $last->copy()->timezone($tz)->format('g:i A'),
                $reminded->min()->copy()->addMinutes($cooldown)->timezone($tz)->format('g:i A'),
            ));
        }

        $studentIds = EpisodeAnalytics::studentIds($free);
        $progress = EpisodeAnalytics::progressOf($episode, $studentIds);
        $recipients = $studentIds->reject(fn (int $id) => $audience === 'not_finished'
            ? $progress->get($id)?->completed_at !== null
            : $progress->has($id));

        if ($recipients->isEmpty()) {
            $this->fail($audience === 'not_finished'
                ? 'Everyone in this group has already finished it.'
                : 'Everyone in this group has already started it.');
        }

        // Only the sections someone was reminded in start their hour
        $remindedSections = SectionStudent::whereIn('section_id', $free)
            ->whereIn('user_id', $recipients)
            ->where('is_active', true)
            ->distinct()
            ->pluck('section_id');
        foreach ($remindedSections as $sectionId) {
            EpisodeSectionReminder::updateOrCreate(
                ['episode_id' => $episode->id, 'section_id' => $sectionId],
                ['last_reminded_at' => now()],
            );
        }

        $episode->loadMissing(['course', 'series', 'tenant']);
        Notification::send(User::whereIn('id', $recipients)->get(), new EpisodeReminder($episode, $audience));

        $names = Section::whereIn('id', $reminded->keys())->pluck('name', 'id');

        return [
            'sent' => $recipients->count(),
            'held' => $reminded
                ->map(fn (Carbon $at, int $id) => ['name' => (string) $names[$id], 'until' => $at->copy()->addMinutes($cooldown)])
                ->sortBy('name')
                ->values()
                ->all(),
        ];
    }

    /**
     * "Reminder sent to 12 students." plus any section left out for its hourly limit.
     *
     * @param  array{sent: int, held: list<array{name: string, until: Carbon}>}  $result
     */
    public static function message(array $result, string $tz): string
    {
        $message = "Reminder sent to {$result['sent']} ".str('student')->plural($result['sent']).'.';

        foreach ($result['held'] as $held) {
            $message .= " {$held['name']} was reminded within the hour, so it was left out until "
                .$held['until']->copy()->timezone($tz)->format('g:i A').'.';
        }

        return $message;
    }

    private function fail(string $message): never
    {
        throw new RuntimeException($message);
    }
}
