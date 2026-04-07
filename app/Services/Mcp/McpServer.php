<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;

class McpServer
{
    private const PROTOCOL_VERSION = '2024-11-05';

    /** @return array<string, mixed>|null */
    public function handle(array $message): ?array
    {
        $method = $message['method'] ?? null;
        $id = $message['id'] ?? null;
        $params = $message['params'] ?? [];

        // Notifications have no id and need no response
        if ($id === null && str_starts_with((string) $method, 'notifications/')) {
            return null;
        }

        return match ($method) {
            'initialize'     => $this->handleInitialize($id, $params),
            'ping'           => $this->ok($id, new \stdClass()),
            'tools/list'     => $this->handleToolsList($id),
            'tools/call'     => $this->handleToolsCall($id, $params),
            default          => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    // -------------------------------------------------------------------------
    // Protocol handlers
    // -------------------------------------------------------------------------

    private function handleInitialize(mixed $id, array $params): array
    {
        return $this->ok($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => ['tools' => new \stdClass()],
            'serverInfo'      => [
                'name'    => 'lectura-mcp',
                'version' => '1.0.0',
            ],
            'instructions' => 'Lectura MCP server — provides file, database, and Artisan access for the Lectura Laravel project.',
        ]);
    }

    private function handleToolsList(mixed $id): array
    {
        return $this->ok($id, ['tools' => $this->toolDefinitions()]);
    }

    private function handleToolsCall(mixed $id, array $params): array
    {
        $name = $params['name'] ?? null;
        $args = $params['arguments'] ?? [];

        try {
            $result = match ($name) {
                'read_file'        => $this->toolReadFile($args),
                'write_file'       => $this->toolWriteFile($args),
                'list_directory'   => $this->toolListDirectory($args),
                'search_in_files'  => $this->toolSearchInFiles($args),
                'run_artisan'      => $this->toolRunArtisan($args),
                'db_read'          => $this->toolDbRead($args),
                'db_create'        => $this->toolDbCreate($args),
                'db_update'        => $this->toolDbUpdate($args),
                'db_delete'        => $this->toolDbDelete($args),
                default            => throw new \InvalidArgumentException("Unknown tool: {$name}"),
            };

            return $this->ok($id, [
                'content' => [['type' => 'text', 'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]],
            ]);
        } catch (\Throwable $e) {
            return $this->ok($id, [
                'content' => [['type' => 'text', 'text' => 'Error: ' . $e->getMessage()]],
                'isError'  => true,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Tools
    // -------------------------------------------------------------------------

    private function toolReadFile(array $args): string
    {
        $path = $this->safePath($args['path'] ?? '');
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: {$args['path']}");
        }
        if (is_dir($path)) {
            throw new \RuntimeException("Path is a directory, use list_directory instead.");
        }
        $size = filesize($path);
        if ($size > 1_000_000) {
            throw new \RuntimeException("File too large ({$size} bytes). Max 1 MB.");
        }
        return file_get_contents($path);
    }

    private function toolWriteFile(array $args): string
    {
        $path = $this->safePath($args['path'] ?? '');
        $content = $args['content'] ?? '';

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);
        return "Written {$args['path']} (" . strlen($content) . " bytes)";
    }

    private function toolListDirectory(array $args): string
    {
        $relPath = $args['path'] ?? '.';
        $path = $this->safePath($relPath);

        if (!is_dir($path)) {
            throw new \RuntimeException("Not a directory: {$relPath}");
        }

        $items = [];
        foreach (new \DirectoryIterator($path) as $item) {
            if ($item->isDot()) {
                continue;
            }
            $type = $item->isDir() ? 'd' : 'f';
            $size = $item->isFile() ? $item->getSize() : 0;
            $items[] = sprintf('%s  %-50s  %d', $type, $item->getFilename(), $size);
        }

        sort($items);
        return implode("\n", $items);
    }

    private function toolSearchInFiles(array $args): string
    {
        $pattern  = $args['pattern'] ?? '';
        $relPath  = $args['path'] ?? '.';
        $ext      = $args['extension'] ?? null;
        $maxLines = (int) ($args['max_results'] ?? 50);

        $path = $this->safePath($relPath);

        if (empty($pattern)) {
            throw new \InvalidArgumentException("pattern is required");
        }

        $results = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($ext !== null && $file->getExtension() !== ltrim($ext, '.')) {
                continue;
            }
            if ($file->getSize() > 500_000) {
                continue;
            }
            // Skip vendor/node_modules
            $rel = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());
            if (str_starts_with($rel, 'vendor') || str_starts_with($rel, 'node_modules') || str_starts_with($rel, '.git')) {
                continue;
            }

            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
            foreach ($lines as $lineNum => $line) {
                if (stripos($line, $pattern) !== false) {
                    $relFile = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $results[] = "{$relFile}:" . ($lineNum + 1) . ": {$line}";
                    if (count($results) >= $maxLines) {
                        break 2;
                    }
                }
            }
        }

        return empty($results)
            ? "No matches found for '{$pattern}'"
            : implode("\n", $results);
    }

    private function toolRunArtisan(array $args): string
    {
        $command = trim($args['command'] ?? '');
        $arguments = $args['arguments'] ?? [];

        $this->assertArtisanAllowed($command);

        $output = new BufferedOutput();
        Artisan::call($command, $arguments, $output);

        $text = $output->fetch();
        return $text !== '' ? $text : "(no output)";
    }

    private function toolDbRead(array $args): string
    {
        $table  = $this->safeIdentifier($args['table'] ?? '');
        $where  = $args['where'] ?? [];
        $limit  = min((int) ($args['limit'] ?? 20), 100);
        $select = $args['select'] ?? ['*'];

        $query = DB::table($table);
        foreach ($where as $col => $val) {
            $query->where($this->safeIdentifier((string) $col), $val);
        }

        $rows = $query->select($select)->limit($limit)->get()->toArray();

        if (empty($rows)) {
            return "No records found in {$table}.";
        }

        return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function toolDbCreate(array $args): string
    {
        $table = $this->safeIdentifier($args['table'] ?? '');
        $data  = $args['data'] ?? [];

        if (empty($data)) {
            throw new \InvalidArgumentException("data is required");
        }

        $id = DB::table($table)->insertGetId($data);
        return "Created record in {$table} with id={$id}";
    }

    private function toolDbUpdate(array $args): string
    {
        $table = $this->safeIdentifier($args['table'] ?? '');
        $id    = $args['id'] ?? null;
        $data  = $args['data'] ?? [];

        if ($id === null) {
            throw new \InvalidArgumentException("id is required");
        }
        if (empty($data)) {
            throw new \InvalidArgumentException("data is required");
        }

        $affected = DB::table($table)->where('id', $id)->update($data);
        return "Updated {$affected} record(s) in {$table} where id={$id}";
    }

    private function toolDbDelete(array $args): string
    {
        $table = $this->safeIdentifier($args['table'] ?? '');
        $id    = $args['id'] ?? null;

        if ($id === null) {
            throw new \InvalidArgumentException("id is required");
        }

        $affected = DB::table($table)->where('id', $id)->delete();
        return "Deleted {$affected} record(s) from {$table} where id={$id}";
    }

    // -------------------------------------------------------------------------
    // Tool schema definitions
    // -------------------------------------------------------------------------

    private function toolDefinitions(): array
    {
        return [
            [
                'name'        => 'read_file',
                'description' => 'Read the contents of a file in the Lectura project. Path is relative to the project root.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Relative path from project root, e.g. app/Models/Course.php'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name'        => 'write_file',
                'description' => 'Write or overwrite a file in the Lectura project. Creates parent directories if needed.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'path'    => ['type' => 'string', 'description' => 'Relative path from project root'],
                        'content' => ['type' => 'string', 'description' => 'File content to write'],
                    ],
                    'required' => ['path', 'content'],
                ],
            ],
            [
                'name'        => 'list_directory',
                'description' => 'List files and directories at a path within the project.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Relative path from project root (default: project root)'],
                    ],
                ],
            ],
            [
                'name'        => 'search_in_files',
                'description' => 'Search for a string pattern in project files (excludes vendor, node_modules, .git).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'pattern'     => ['type' => 'string', 'description' => 'String to search for (case-insensitive)'],
                        'path'        => ['type' => 'string', 'description' => 'Directory to search in (default: project root)'],
                        'extension'   => ['type' => 'string', 'description' => 'Filter by file extension, e.g. php, blade.php'],
                        'max_results' => ['type' => 'integer', 'description' => 'Maximum lines to return (default: 50)'],
                    ],
                    'required' => ['pattern'],
                ],
            ],
            [
                'name'        => 'run_artisan',
                'description' => 'Run an Artisan command. Only whitelisted commands are permitted.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'command'   => ['type' => 'string', 'description' => 'Artisan command, e.g. route:list, migrate:status, make:model'],
                        'arguments' => [
                            'type'                 => 'object',
                            'description'          => 'Named arguments/options as key-value pairs',
                            'additionalProperties' => true,
                        ],
                    ],
                    'required' => ['command'],
                ],
            ],
            [
                'name'        => 'db_read',
                'description' => 'Read records from a database table.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'table'  => ['type' => 'string', 'description' => 'Table name'],
                        'where'  => ['type' => 'object', 'description' => 'Column => value conditions', 'additionalProperties' => true],
                        'select' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Columns to select (default: all)'],
                        'limit'  => ['type' => 'integer', 'description' => 'Max rows (default: 20, max: 100)'],
                    ],
                    'required' => ['table'],
                ],
            ],
            [
                'name'        => 'db_create',
                'description' => 'Insert a new record into a database table.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'table' => ['type' => 'string', 'description' => 'Table name'],
                        'data'  => ['type' => 'object', 'description' => 'Column => value pairs to insert', 'additionalProperties' => true],
                    ],
                    'required' => ['table', 'data'],
                ],
            ],
            [
                'name'        => 'db_update',
                'description' => 'Update a record in a database table by id.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'table' => ['type' => 'string', 'description' => 'Table name'],
                        'id'    => ['type' => 'integer', 'description' => 'Record id'],
                        'data'  => ['type' => 'object', 'description' => 'Columns to update', 'additionalProperties' => true],
                    ],
                    'required' => ['table', 'id', 'data'],
                ],
            ],
            [
                'name'        => 'db_delete',
                'description' => 'Delete a record from a database table by id.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'table' => ['type' => 'string', 'description' => 'Table name'],
                        'id'    => ['type' => 'integer', 'description' => 'Record id'],
                    ],
                    'required' => ['table', 'id'],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function safePath(string $rel): string
    {
        $root = realpath(config('mcp.file_root', base_path()));
        // Normalise separators, strip leading slash/backslash
        $rel = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel), DIRECTORY_SEPARATOR);
        $full = $root . DIRECTORY_SEPARATOR . $rel;
        $real = realpath($full);

        // If file doesn't exist yet (write), resolve parent
        if ($real === false) {
            $parent = realpath(dirname($full));
            if ($parent === false || !str_starts_with($parent, $root)) {
                throw new \RuntimeException("Path outside project root: {$rel}");
            }
            return $full;
        }

        if (!str_starts_with($real, $root)) {
            throw new \RuntimeException("Path outside project root: {$rel}");
        }

        return $real;
    }

    private function safeIdentifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid identifier: {$name}");
        }
        return $name;
    }

    private function assertArtisanAllowed(string $command): void
    {
        $allowed = config('mcp.allowed_artisan', []);
        foreach ($allowed as $prefix) {
            if (str_starts_with($command, $prefix)) {
                return;
            }
        }
        throw new \RuntimeException("Artisan command not allowed: {$command}");
    }

    private function ok(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
