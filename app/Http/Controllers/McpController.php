<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpController extends Controller
{
    /**
     * GET /mcp — discovery endpoint.
     * Claude Code probes this before POSTing; also shows server info.
     */
    public function discover(): JsonResponse
    {
        $base = rtrim(config('app.url'), '/');

        return response()->json([
            'name'              => 'lectura-mcp',
            'version'           => '1.0.0',
            'transport'         => 'streamable-http',
            'endpoint'          => "{$base}/mcp",
            'oauth_metadata'    => "{$base}/.well-known/oauth-authorization-server",
            'auth'              => 'Bearer token — use MCP_SECRET or obtain via /oauth/token',
        ], 200, [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
        ]);
    }

    public function handle(Request $request, McpServer $server): JsonResponse|Response|StreamedResponse
    {
        // ── Auth — accepts static secret OR an OAuth access token ────────────
        if (!$this->authenticate($request)) {
            return response()->json(
                ['error' => 'Unauthorized'],
                401,
                ['WWW-Authenticate' => 'Bearer realm="mcp", error="invalid_token"']
            );
        }

        // ── Parse body ────────────────────────────────────────────────────────
        $body = $request->json()->all();
        if (empty($body)) {
            return response()->json(
                ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']],
                400
            );
        }

        // ── Notification (no id) — ack and return ─────────────────────────────
        if (!array_key_exists('id', $body)) {
            $server->handle($body);
            return response('', 202);
        }

        // ── Process ───────────────────────────────────────────────────────────
        $result = $server->handle($body);

        // ── Respond: SSE or JSON ──────────────────────────────────────────────
        $acceptsSse = str_contains($request->header('Accept', ''), 'text/event-stream');

        if ($acceptsSse) {
            $payload = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return response()->stream(function () use ($payload): void {
                echo "data: {$payload}\n\n";
                flush();
            }, 200, [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Connection'        => 'keep-alive',
            ]);
        }

        return response()->json($result, 200, [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
        ]);
    }

    // -------------------------------------------------------------------------
    // Auth helper
    // -------------------------------------------------------------------------

    private function authenticate(Request $request): bool
    {
        $token = $request->bearerToken();
        if (empty($token)) {
            return false;
        }

        // 1. Static secret (simple setup)
        $secret = config('mcp.secret');
        if (!empty($secret) && hash_equals($secret, $token)) {
            return true;
        }

        // 2. OAuth 2.0 access token issued by /oauth/token
        return Cache::has("mcp_oauth_token:{$token}");
    }

    /** Handle pre-flight CORS requests */
    public function preflight(): Response
    {
        return response('', 204, [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept',
            'Access-Control-Max-Age'       => '86400',
        ]);
    }
}
