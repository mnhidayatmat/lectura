<?php

declare(strict_types=1);

namespace App\Services\Attendance;

class QrCodeService
{
    /**
     * Generate a token for the current time window.
     * Token changes every $rotationSeconds.
     */
    public function generateToken(string $secret, int $rotationSeconds = 30): string
    {
        $window = (int) floor(time() / $rotationSeconds);
        return hash_hmac('sha256', (string) $window, $secret);
    }

    /**
     * Validate a token — accepts current window or previous window (grace period).
     */
    public function validateToken(string $token, string $secret, int $rotationSeconds = 30): bool
    {
        $currentWindow = (int) floor(time() / $rotationSeconds);

        // Check current window
        $currentToken = hash_hmac('sha256', (string) $currentWindow, $secret);
        if (hash_equals($currentToken, $token)) {
            return true;
        }

        // Check previous window (grace period)
        $previousToken = hash_hmac('sha256', (string) ($currentWindow - 1), $secret);
        return hash_equals($previousToken, $token);
    }

    /**
     * Build the QR payload for encoding.
     */
    public function buildPayload(int $sessionId, string $token): string
    {
        return json_encode([
            's' => $sessionId,
            't' => $token,
            'ts' => time(),
        ]);
    }

    /**
     * Parse QR payload from student scan.
     */
    public function parsePayload(string $payload): ?array
    {
        $data = json_decode($payload, true);

        if (! $data || ! isset($data['s'], $data['t'])) {
            return null;
        }

        return [
            'session_id' => (int) $data['s'],
            'token' => $data['t'],
            'timestamp' => $data['ts'] ?? 0,
        ];
    }
}
