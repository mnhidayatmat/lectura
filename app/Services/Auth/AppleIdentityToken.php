<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Verifies the identity token returned by the native Sign in with Apple sheet.
 *
 * Unlike Google, the mobile app never leaves the device: Apple hands it a signed
 * JWT, which this class checks against Apple's published keys. No client secret,
 * Services ID or redirect URI is involved — only the bundle id, as the audience.
 */
class AppleIdentityToken
{
    public const KEYS_URL = 'https://appleid.apple.com/auth/keys';

    public const KEYS_CACHE_KEY = 'apple_sign_in:jwks';

    /**
     * @param  string|null  $rawNonce  the unhashed nonce the app generated; the token carries its SHA-256
     * @return array{sub: string, aud: string, email: ?string, email_verified: bool, is_private_email: bool}
     */
    public function verify(string $identityToken, ?string $rawNonce = null): array
    {
        $clientIds = collect(explode(',', (string) config('services.apple.client_ids')))
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->all();

        if ($clientIds === []) {
            throw new RuntimeException('services.apple.client_ids is not configured.');
        }

        // Firebase\JWT verifies the signature, `exp` and `iat` for us; everything
        // below is the part Apple asks us to check ourselves.
        $claims = (array) JWT::decode($identityToken, JWK::parseKeySet($this->keys()));

        if (($claims['iss'] ?? null) !== 'https://appleid.apple.com') {
            throw new RuntimeException('Unexpected identity token issuer.');
        }

        if (! in_array($claims['aud'] ?? null, $clientIds, true)) {
            throw new RuntimeException('Identity token was issued for another app.');
        }

        if ($rawNonce !== null && ! hash_equals(hash('sha256', $rawNonce), (string) ($claims['nonce'] ?? ''))) {
            throw new RuntimeException('Identity token nonce does not match.');
        }

        $sub = (string) ($claims['sub'] ?? '');

        if ($sub === '') {
            throw new RuntimeException('Identity token has no subject.');
        }

        return [
            'sub' => $sub,
            // Carried out so the token exchange signs its client secret for the very
            // bundle the sheet ran in.
            'aud' => (string) $claims['aud'],
            'email' => ($email = $claims['email'] ?? null) ? Str::lower((string) $email) : null,
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_private_email' => filter_var($claims['is_private_email'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Apple's public signing keys. They rotate rarely, so a day of cache keeps
     * sign-in off the network without stranding us on a retired key.
     *
     * @return array<string, mixed>
     */
    protected function keys(): array
    {
        return Cache::remember(self::KEYS_CACHE_KEY, now()->addDay(), function (): array {
            $response = Http::timeout(10)->get(self::KEYS_URL);

            if (! $response->successful() || ! is_array($response->json('keys'))) {
                throw new RuntimeException('Could not fetch Apple\'s signing keys.');
            }

            return $response->json();
        });
    }
}
