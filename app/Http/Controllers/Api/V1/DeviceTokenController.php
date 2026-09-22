<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Registers this install's Firebase Cloud Messaging token for push notifications.
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios'],
        ]);

        $accessToken = $request->user()->currentAccessToken();

        // A token belongs to one install, so whoever signed in last on it owns it.
        DeviceToken::updateOrCreate(
            ['token' => $request->token],
            [
                'user_id' => $request->user()->id,
                'personal_access_token_id' => $accessToken instanceof PersonalAccessToken && $accessToken->exists ? $accessToken->id : null,
                'platform' => $request->platform,
            ],
        );

        return response()->json(['message' => 'Device registered.', 'data' => ['token' => $request->token]]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $request->user()->deviceTokens()->where('token', $request->token)->delete();

        return response()->json(['message' => 'Device removed.', 'data' => ['token' => $request->token]]);
    }
}
