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
        $rotationSeconds = max(1, $rotationSeconds);
        $window = (int) floor(now()->getTimestamp() / $rotationSeconds);

        return hash_hmac('sha256', (string) $window, $secret);
    }

    /**
     * Validate a token — accepts the current window, and the previous window only
     * during the opening seconds of the current one.
     *
     * The previous-window grace exists so a student who scans just as the code
     * rotates still checks in. Honouring it for the whole window doubled the life
     * of a photographed code to 2 x rotation; limiting it to the hand-over moment
     * keeps those scans working while shortening the replay window.
     */
    public function validateToken(string $token, string $secret, int $rotationSeconds = 30, ?int $atTimestamp = null): bool
    {
        $rotationSeconds = max(1, $rotationSeconds);

        // A check-in queued while offline is validated against the moment it was
        // scanned, not the moment it arrives — otherwise the token is always
        // expired by the time the phone reconnects. Callers must bound how old
        // that instant may be; this method trusts what it is given.
        $now = $atTimestamp ?? now()->getTimestamp();
        $currentWindow = (int) floor($now / $rotationSeconds);

        $currentToken = hash_hmac('sha256', (string) $currentWindow, $secret);
        if (hash_equals($currentToken, $token)) {
            return true;
        }

        if ($now % $rotationSeconds >= $this->graceSeconds($rotationSeconds)) {
            return false;
        }

        $previousToken = hash_hmac('sha256', (string) ($currentWindow - 1), $secret);

        return hash_equals($previousToken, $token);
    }

    /**
     * How long into a new window the superseded code is still honoured.
     */
    public function graceSeconds(int $rotationSeconds): int
    {
        return max(1, min(10, intdiv(max(1, $rotationSeconds), 3)));
    }

    /**
     * Build the QR payload for encoding.
     */
    public function buildPayload(int $sessionId, string $token): string
    {
        return json_encode([
            's' => $sessionId,
            't' => $token,
            'ts' => now()->getTimestamp(),
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
