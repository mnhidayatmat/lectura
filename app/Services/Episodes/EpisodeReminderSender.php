<?php

declare(strict_types=1);

namespace App\Services\Episodes;

use App\Models\Episode;
use App\Models\User;
use App\Notifications\EpisodeReminder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Lecturer-triggered nudge to the students who haven't started (or finished) an episode.
 */
final class EpisodeReminderSender
{
    /**
     * @param  Collection<int, int>  $studentIds  The lecturer's students
     *
     * @throws RuntimeException with a message to show the lecturer as is
     */
    public function send(Episode $episode, Collection $studentIds, string $audience): int
    {
        if (! $episode->isAvailable()) {
            $this->fail('This episode is not released yet.');
        }

        if ($next = EpisodeAnalytics::reminderAvailableAt($episode)) {
            $tz = $episode->tenant?->timezone ?: config('app.timezone');
            $this->fail(sprintf(
                'A reminder went out at %s. You can send another after %s.',
                $episode->last_reminded_at->copy()->timezone($tz)->format('g:i A'),
                $next->copy()->timezone($tz)->format('g:i A'),
            ));
        }

        $progress = EpisodeAnalytics::progressOf($episode, $studentIds);
        $recipients = $studentIds->reject(fn (int $id) => $audience === 'not_finished'
            ? $progress->get($id)?->completed_at !== null
            : $progress->has($id));

        if ($recipients->isEmpty()) {
            $this->fail($audience === 'not_finished'
                ? 'Everyone in this group has already finished it.'
                : 'Everyone in this group has already started it.');
        }

        $episode->forceFill(['last_reminded_at' => now()])->save();
        $episode->loadMissing(['course', 'series', 'tenant']);
        Notification::send(User::whereIn('id', $recipients)->get(), new EpisodeReminder($episode, $audience));

        return $recipients->count();
    }

    private function fail(string $message): never
    {
        throw new RuntimeException($message);
    }
}
