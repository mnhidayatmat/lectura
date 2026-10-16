<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\User;
use App\Notifications\AssessmentPublished;
use Illuminate\Support\Facades\Notification;

/**
 * Tells enrolled students, once, when work they must hand in is released:
 * an assessment that requires a submission becomes active (or completed).
 */
final class AssessmentAnnouncer
{
    public function announceIfReleased(Assessment $assessment): bool
    {
        if ($assessment->announced_at !== null
            || ! $assessment->notify_students
            || ! $assessment->requires_submission
            || ! in_array($assessment->status, ['active', 'completed'], true)) {
            return false;
        }

        // Claim it first so two saves in parallel cannot both send.
        $claimed = Assessment::withoutGlobalScopes()
            ->whereKey($assessment->id)
            ->whereNull('announced_at')
            ->update(['announced_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $assessment->announced_at = now();
        $assessment->loadMissing(['course', 'tenant']);

        $students = User::whereHas('sections', fn ($q) => $q
            ->where('sections.course_id', $assessment->course_id)
            ->where('section_students.is_active', true))
            ->get();

        Notification::send($students, new AssessmentPublished($assessment));

        return true;
    }
}
