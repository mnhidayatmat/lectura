<?php

declare(strict_types=1);

namespace App\Services\Episodes;

use App\Models\Episode;
use App\Models\User;
use App\Notifications\EpisodePublished;
use Illuminate\Support\Facades\Notification;

/**
 * Tells enrolled students when an episode becomes watchable, once.
 */
final class EpisodeAnnouncer
{
    /**
     * Announce the episode if it is available, wants announcing and hasn't been yet.
     */
    public function announceIfDue(Episode $episode): bool
    {
        if ($episode->announced_at !== null || ! $episode->notify_students || ! $episode->isAvailable()) {
            return false;
        }

        // Claim it first so a parallel scheduler run cannot send twice.
        $claimed = Episode::withoutGlobalScopes()
            ->whereKey($episode->id)
            ->whereNull('announced_at')
            ->update(['announced_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $episode->announced_at = now();
        $episode->loadMissing(['course', 'series', 'tenant']);

        $students = User::whereHas('sections', fn ($q) => $q
            ->where('sections.course_id', $episode->course_id)
            ->where('section_students.is_active', true))
            ->get();

        Notification::send($students, new EpisodePublished($episode));

        return true;
    }

    /**
     * Scheduled releases whose time has come. Runs from the scheduler.
     */
    public function announceDue(): int
    {
        return Episode::withoutGlobalScopes()
            ->whereIn('status', Episode::VISIBLE_STATUSES)
            ->where('notify_students', true)
            ->whereNull('announced_at')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->get()
            ->filter(fn (Episode $episode) => $this->announceIfDue($episode))
            ->count();
    }
}
