<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Minimal OAuth 2.0 Authorization Server for the MCP endpoint.
 *
 * Supports the client_credentials grant only.
 * Tokens are random strings stored in the cache with a configurable TTL.
 */
class McpOAuthController extends Controller
{
    /**
     * RFC 8414 — OAuth Authorization Server Metadata
     * GET /.well-known/oauth-authorization-server
     */
    public function metadata(Request $request): JsonResponse
    {
        $base = rtrim(config('app.url'), '/');

        return response()->json([
            'issuer'                                     => $base,
            'token_endpoint'                             => "{$base}/oauth/token",
            'token_endpoint_auth_methods_supported'      => ['client_secret_post', 'client_secret_basic'],
            'grant_types_supported'                      => ['client_credentials'],
            'scopes_supported'                           => ['mcp'],
            'response_types_supported'                   => ['token'],
            'service_documentation'                      => "{$base}/mcp",
        ])->withHeaders($this->corsHeaders());
    }

    /**
     * Token endpoint — issues short-lived Bearer tokens.
     * POST /oauth/token
     *
     * Accepts:
     *  - grant_type  = client_credentials
     *  - client_id   + client_secret  (request body)
     *  - OR HTTP Basic Auth header
     */
    public function token(Request $request): JsonResponse
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

        $grantType = $request->input('grant_type');
        if ($grantType !== 'client_credentials') {
            return $this->oauthError('unsupported_grant_type', 'Only client_credentials is supported.');
        }

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

    /** CORS preflight for token endpoint */
    public function preflight(): \Illuminate\Http\Response
    {
        return response('', 204, array_merge($this->corsHeaders(), [
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept',
            'Access-Control-Max-Age'       => '86400',
        ]));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Extract client_id / client_secret from Basic Auth header or POST body. */
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
