<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Secret
    |--------------------------------------------------------------------------
    | Bearer token required in every request to /mcp.
    | Set MCP_SECRET in your .env file.
    */
    'secret' => env('MCP_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0 Client Credentials
    |--------------------------------------------------------------------------
    | Used by the /oauth/token endpoint (client_credentials grant).
    | Set MCP_OAUTH_CLIENT_ID and MCP_OAUTH_CLIENT_SECRET in .env.
    | MCP_OAUTH_TOKEN_TTL controls how long (seconds) an issued token lives.
    |
    | Leave blank to disable OAuth — the static MCP_SECRET will still work.
    */
    'oauth' => [
        'client_id'     => env('MCP_OAUTH_CLIENT_ID'),
        'client_secret' => env('MCP_OAUTH_CLIENT_SECRET'),
        'token_ttl'     => (int) env('MCP_OAUTH_TOKEN_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Artisan Commands
    |--------------------------------------------------------------------------
    | Only these commands (prefix-matched) can be run via the run_artisan tool.
    */
    'allowed_artisan' => [
        'route:list',
        'migrate:status',
        'migrate:fresh',
        'migrate',
        'db:seed',
        'cache:clear',
        'config:clear',
        'view:clear',
        'queue:restart',
        'make:',
        'tinker',
        'about',
        'inspire',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Operation Root
    |--------------------------------------------------------------------------
    | All file read/write operations are confined to this directory.
    */
    'file_root' => base_path(),
];
