<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * OAuth 2.0 Authorization Server for the MCP endpoint.
 *
 * Supports:
 *  - Authorization Code + PKCE  (used by Claude.ai remote connector)
 *  - Client Credentials         (used by Claude Code CLI / direct API)
 */
class McpOAuthController extends Controller
{
    // =========================================================================
    // Discovery endpoints
    // =========================================================================

    /**
     * RFC 9728 — OAuth Protected Resource Metadata.
     * GET /.well-known/oauth-protected-resource
     * GET /.well-known/oauth-protected-resource/mcp
     *
     * Tells the client which authorization server protects this resource.
     */
    public function protectedResourceMetadata(): JsonResponse
    {
        $base = rtrim(config('app.url'), '/');

        return response()->json([
            'resource'              => "{$base}/mcp",
            'authorization_servers' => [$base],
            'scopes_supported'      => ['mcp'],
            'bearer_methods_supported' => ['header'],
        ])->withHeaders($this->corsHeaders());
    }

    /**
     * RFC 8414 — OAuth Authorization Server Metadata.
     * GET /.well-known/oauth-authorization-server
     */
    public function metadata(): JsonResponse
    {
        $base = rtrim(config('app.url'), '/');

        return response()->json([
            'issuer'                                => $base,
            'authorization_endpoint'                => "{$base}/authorize",
            'token_endpoint'                        => "{$base}/oauth/token",
            'registration_endpoint'                 => "{$base}/oauth/register",
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic', 'none'],
            'grant_types_supported'                 => ['authorization_code', 'client_credentials'],
            'response_types_supported'              => ['code'],
            'code_challenge_methods_supported'      => ['S256'],
            'scopes_supported'                      => ['mcp'],
            'service_documentation'                 => "{$base}/mcp",
        ])->withHeaders($this->corsHeaders());
    }

    // =========================================================================
    // Dynamic Client Registration (RFC 7591 — simplified)
    // =========================================================================

    /**
     * POST /oauth/register
     *
     * Claude.ai may dynamically register before starting the auth flow.
     * We accept any registration and return the same client_id back.
     */
    public function register(Request $request): JsonResponse
    {
        $clientName  = $request->input('client_name', 'mcp-client');
        $redirectUris = $request->input('redirect_uris', []);

        // Generate a dynamic client_id (or reuse the configured one)
        $clientId = Str::random(32);

        // Store the registration in cache (24h)
        Cache::put("mcp_oauth_client:{$clientId}", [
            'client_name'   => $clientName,
            'redirect_uris' => $redirectUris,
            'grant_types'   => ['authorization_code'],
        ], 86400);

        return response()->json([
            'client_id'                  => $clientId,
            'client_name'                => $clientName,
            'redirect_uris'              => $redirectUris,
            'grant_types'                => ['authorization_code'],
            'response_types'             => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], 201)->withHeaders($this->corsHeaders());
    }

    // =========================================================================
    // Authorization endpoint (Authorization Code + PKCE)
    // =========================================================================

    /**
     * GET /authorize
     *
     * Handles the authorization request from Claude.ai.
     * Auto-approves if the client_id is valid (no interactive consent needed
     * for MCP server-to-server connections).
     */
    public function authorize(Request $request): JsonResponse|RedirectResponse
    {
        $responseType       = $request->input('response_type');
        $clientId           = $request->input('client_id');
        $redirectUri        = $request->input('redirect_uri');
        $codeChallenge      = $request->input('code_challenge');
        $codeChallengeMethod = $request->input('code_challenge_method', 'S256');
        $state              = $request->input('state');
        $scope              = $request->input('scope', 'mcp');

        // Validate response_type
        if ($responseType !== 'code') {
            return $this->oauthError('unsupported_response_type', 'Only "code" is supported.');
        }

        // Validate client_id — accept configured client OR dynamically registered
        if (!$this->isValidClient($clientId)) {
            return $this->oauthError('invalid_client', 'Unknown client_id.', 401);
        }

        // PKCE is required
        if (empty($codeChallenge)) {
            return $this->oauthError('invalid_request', 'code_challenge is required (PKCE).');
        }

        // Generate authorization code (single-use, 5 min TTL)
        $code = Str::random(48);
        Cache::put("mcp_auth_code:{$code}", [
            'client_id'             => $clientId,
            'redirect_uri'          => $redirectUri,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'scope'                 => $scope,
        ], 300);

        // Redirect back to Claude.ai callback with the code
        $params = http_build_query(array_filter([
            'code'  => $code,
            'state' => $state,
        ]));

        return redirect("{$redirectUri}?{$params}");
    }

    // =========================================================================
    // Token endpoint
    // =========================================================================

    /**
     * POST /oauth/token
     *
     * Supports:
     *  - grant_type=authorization_code (with PKCE verification)
     *  - grant_type=client_credentials
     */
    public function token(Request $request): JsonResponse
    {
        $grantType = $request->input('grant_type');

        return match ($grantType) {
            'authorization_code' => $this->handleAuthorizationCodeGrant($request),
            'client_credentials' => $this->handleClientCredentialsGrant($request),
            default              => $this->oauthError('unsupported_grant_type', "Unsupported grant_type: {$grantType}"),
        };
    }

    /** CORS preflight */
    public function preflight(): \Illuminate\Http\Response
    {
        return response('', 204, array_merge($this->corsHeaders(), [
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept',
            'Access-Control-Max-Age'       => '86400',
        ]));
    }

    // =========================================================================
    // Grant handlers
    // =========================================================================

    private function handleAuthorizationCodeGrant(Request $request): JsonResponse
    {
        $code         = $request->input('code');
        $codeVerifier = $request->input('code_verifier');
        $clientId     = $request->input('client_id');
        $redirectUri  = $request->input('redirect_uri');

        // Pull the stored auth code (single-use — deleted on retrieval)
        $stored = Cache::pull("mcp_auth_code:{$code}");
        if (!$stored) {
            return $this->oauthError('invalid_grant', 'Invalid or expired authorization code.', 400);
        }

        // Validate client_id
        if ($stored['client_id'] !== $clientId) {
            return $this->oauthError('invalid_grant', 'Client ID mismatch.', 400);
        }

        // Validate redirect_uri
        if ($stored['redirect_uri'] !== $redirectUri) {
            return $this->oauthError('invalid_grant', 'Redirect URI mismatch.', 400);
        }

        // Validate PKCE code_verifier
        if (empty($codeVerifier)) {
            return $this->oauthError('invalid_request', 'code_verifier is required.', 400);
        }
        if (!$this->verifyPkce($codeVerifier, $stored['code_challenge'], $stored['code_challenge_method'])) {
            return $this->oauthError('invalid_grant', 'PKCE verification failed.', 400);
        }

        // Issue access token
        return $this->issueToken($clientId);
    }

    private function handleClientCredentialsGrant(Request $request): JsonResponse
    {
        [$clientId, $clientSecret] = $this->extractClientCredentials($request);

        if (
            empty($clientId)
            || empty($clientSecret)
            || $clientId     !== config('mcp.oauth.client_id')
            || $clientSecret !== config('mcp.oauth.client_secret')
        ) {
            return $this->oauthError('invalid_client', 'Invalid client credentials.', 401);
        }

        return $this->issueToken($clientId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function issueToken(string $clientId): JsonResponse
    {
        $ttl   = (int) config('mcp.oauth.token_ttl', 3600);
        $token = Str::random(64);

        Cache::put("mcp_oauth_token:{$token}", $clientId, $ttl);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $ttl,
            'scope'        => 'mcp',
        ])->withHeaders($this->corsHeaders());
    }

    private function isValidClient(string $clientId): bool
    {
        // Configured static client
        if ($clientId === config('mcp.oauth.client_id')) {
            return true;
        }

        // Dynamically registered client
        return Cache::has("mcp_oauth_client:{$clientId}");
    }

    private function verifyPkce(string $codeVerifier, string $codeChallenge, string $method): bool
    {
        if ($method !== 'S256') {
            return false;
        }

        $hash     = hash('sha256', $codeVerifier, true);
        $computed = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        return hash_equals($codeChallenge, $computed);
    }

    private function extractClientCredentials(Request $request): array
    {
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Basic ')) {
            $decoded = base64_decode(substr($authHeader, 6));
            [$id, $secret] = array_pad(explode(':', $decoded, 2), 2, null);
            return [$id, $secret];
        }

        return [
            $request->input('client_id'),
            $request->input('client_secret'),
        ];
    }

    private function oauthError(string $error, string $description, int $status = 400): JsonResponse
    {
        return response()->json([
            'error'             => $error,
            'error_description' => $description,
        ], $status)->withHeaders($this->corsHeaders());
    }

    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
            'Cache-Control'                => 'no-store',
            'Pragma'                       => 'no-cache',
        ];
    }
}
