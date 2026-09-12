<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Apple's token endpoints, used for one thing only: revoking a user's token when
 * they delete their account, which the App Store requires of every app that
 * offers Sign in with Apple.
 *
 * Revoking needs a refresh token, and Apple only issues one in exchange for the
 * authorization code from the sign-in sheet — so the code is exchanged as the
 * user signs in and the refresh token kept until they leave.
 *
 * All of this needs a signing key (.p8). Without one the class reports itself
 * unconfigured and sign-in carries on unaffected; only revocation is lost.
 */
class AppleTokenService
{
    public const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    public const REVOKE_URL = 'https://appleid.apple.com/auth/revoke';

    public function isConfigured(): bool
    {
        return (bool) config('services.apple.team_id')
            && (bool) config('services.apple.key_id')
            && $this->privateKey() !== null;
    }

    /**
     * Trades the sheet's single-use authorization code for a refresh token.
     *
     * @param  string  $clientId  the audience of the identity token, i.e. the bundle id
     */
    public function exchangeAuthorizationCode(string $code, string $clientId): ?string
    {
        $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
            'client_id' => $clientId,
            'client_secret' => $this->clientSecret($clientId),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Apple rejected the authorization code: '.$response->body());
        }

        return $response->json('refresh_token');
    }

    /**
     * Tells Apple the user is gone, so the app stops appearing under their Apple ID.
     *
     * Returns false when there is nothing to revoke or the call failed; the caller
     * is deleting an account and must not be blocked by either.
     */
    public function revoke(User $user): bool
    {
        $token = $user->apple_refresh_token;

        if (! $this->isConfigured() || ! $token || ! $user->apple_id) {
            return false;
        }

        $clientId = $this->defaultClientId();

        $response = Http::asForm()->timeout(10)->post(self::REVOKE_URL, [
            'client_id' => $clientId,
            'client_secret' => $this->clientSecret($clientId),
            'token' => $token,
            'token_type_hint' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Apple refused to revoke the token: '.$response->body());
        }

        return true;
    }

    /**
     * Apple takes no client secret of its own: it is a short-lived JWT signed with
     * the .p8 key, naming the team as issuer and the app as subject.
     */
    protected function clientSecret(string $clientId): string
    {
        $key = $this->privateKey();

        if (! $key) {
            throw new RuntimeException('services.apple.private_key is not configured.');
        }

        return JWT::encode([
            'iss' => (string) config('services.apple.team_id'),
            'iat' => time(),
            'exp' => time() + 3600,
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ], $key, 'ES256', (string) config('services.apple.key_id'));
    }

    /** The first configured bundle id, which is the one a released app signs in with. */
    protected function defaultClientId(): string
    {
        $clientIds = array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) config('services.apple.client_ids')),
        )));

        if ($clientIds === []) {
            throw new RuntimeException('services.apple.client_ids is not configured.');
        }

        return $clientIds[0];
    }

    /** The .p8 contents, from a path or inline, with escaped newlines restored. */
    protected function privateKey(): ?string
    {
        if ($path = config('services.apple.private_key_path')) {
            return is_readable($path) ? (string) file_get_contents($path) : null;
        }

        $key = (string) config('services.apple.private_key');

        return $key === '' ? null : str_replace('\n', "\n", $key);
    }
}
