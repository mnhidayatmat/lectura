<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Live\Concerns;

use App\Models\QuizParticipant;
use App\Models\QuizSession;
use App\Models\SectionStudent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

trait InteractsWithLiveQuizzes
{
    protected function authorizeQuiz(QuizSession $session, User $user): void
    {
        if ($session->lecturer_id === $user->id) {
            return;
        }

        $enrolled = SectionStudent::where('section_id', $session->section_id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $enrolled) {
            abort(403, 'You are not enrolled in this section.');
        }
    }

    protected function isPlayable(QuizSession $session): bool
    {
        return $session->isOffline() ? $session->isOfflineOpen() : $session->isLive();
    }

    protected function registerParticipant(QuizSession $session, User $user): QuizParticipant
    {
        $participant = QuizParticipant::firstOrCreate(
            ['quiz_session_id' => $session->id, 'user_id' => $user->id],
            [
                'display_name' => $session->is_anonymous ? 'Player '.rand(100, 999) : $user->name,
                'joined_at' => now(),
            ]
        );

        if ($participant->wasRecentlyCreated) {
            Cache::forget("quiz_session_{$session->id}_lobby");
        }

        return $participant;
    }

    protected function findParticipant(QuizSession $session, User $user): ?QuizParticipant
    {
        return QuizParticipant::where('quiz_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();
    }
}
