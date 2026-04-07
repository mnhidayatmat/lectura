<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Mcp\McpServer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpController extends Controller
{
    public function handle(Request $request, McpServer $server): Response|StreamedResponse
    {
        // ── Auth ──────────────────────────────────────────────────────────────
        $secret = config('mcp.secret');
        if (empty($secret) || $request->bearerToken() !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
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
