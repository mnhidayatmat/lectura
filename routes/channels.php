<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\WhiteboardController;
use App\Models\ActiveLearningSession;
use App\Models\Whiteboard;
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

Broadcast::channel('whiteboard.{whiteboardId}', function ($user, int $whiteboardId) {
    $board = Whiteboard::withoutGlobalScopes()->find($whiteboardId);

    if (! $board || $board->tenant_id !== app('current_tenant')?->id) {
        return false;
    }

    try {
        app(WhiteboardController::class)->ensureBoardAccess($board);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
