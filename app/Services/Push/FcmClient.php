<?php

declare(strict_types=1);

namespace App\Services\Push;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1, authenticated with a service account.
 *
 * Without a service-account JSON the client reports itself unconfigured and
 * nothing is pushed; notifications still land in the database as before.
 */
class FcmClient
{
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @var array<string, mixed>|null */
    private ?array $credentials = null;

    public function isConfigured(): bool
    {
        $credentials = $this->credentials();

        return isset($credentials['project_id'], $credentials['client_email'], $credentials['private_key']);
    }

    /**
     * Sends one message to one registration token.
     *
     * @param  array<string, string>  $data  FCM only accepts string values
     * @return bool false when FCM says the token is no longer valid, so the caller can drop it
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->credentials()['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => (object) $data,
                    'android' => ['priority' => 'high'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        // UNREGISTERED: the app was uninstalled or the token rotated.
        if ($response->status() === 404 || $response->json('error.details.0.errorCode') === 'UNREGISTERED') {
            return false;
        }

        throw new RuntimeException('FCM rejected the message: '.$response->body());
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();

        return Cache::remember('fcm.access_token.'.md5($credentials['client_email']), now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Google rejected the FCM service account: '.$response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = config('services.fcm.credentials_path');
        if ($path && ! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        $json = $path && is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        return $this->credentials = is_array($json) ? $json : [];
    }
}
