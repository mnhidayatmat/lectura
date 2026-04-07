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
