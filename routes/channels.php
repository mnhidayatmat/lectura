<?php

declare(strict_types=1);

use App\Models\ActiveLearningSession;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('active-learning-session.{sessionId}', function ($user, int $sessionId) {
    $session = ActiveLearningSession::find($sessionId);

    if (! $session) {
        return false;
    }

    // Session creator (lecturer) can always join
    if ($session->created_by === $user->id) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => 'lecturer'];
    }

    // Participants (students who joined)
    if ($session->hasParticipant($user->id)) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => 'student'];
    }

    return false;
});
