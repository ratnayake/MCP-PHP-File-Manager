<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;
use MCP\FileManager\Protocol\Response;

class ListDirectoryTool implements ToolInterface
{
    private FileManager $fm;
    private PathValidator $validator;

    public function __construct(FileManager $fm, PathValidator $validator)
    {
        $this->fm = $fm;
        $this->validator = $validator;
    }

    public function getName(): string
    {
        return 'list_directory';
    }

    public function getDescription(): string
    {
        return 'List files and directories at the given path. Returns names, types, sizes, permissions, and modification times. Paths are relative to the configured root (use "/" or "" for root).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path to list. Use "/" or "" for the root directory.',
                ],
                'recursive' => [
                    'type' => 'boolean',
                    'description' => 'Whether to list subdirectories recursively. Defaults to false.',
                    'default' => false,
                ],
            ],
            'required' => ['path'],
        ];
    }

    public function execute(array $params): array
    {
        $path = $params['path'] ?? null;
        if ($path === null) {
            throw new \InvalidArgumentException('Parameter "path" is required.');
        }

        $recursive = isset($params['recursive']) ? (bool) $params['recursive'] : false;

        $absPath = $this->validator->validate((string) $path);

        if (!is_dir($absPath)) {
            throw new \RuntimeException('Path is not a directory: ' . $path);
        }

        $entries = $this->fm->listDirectory($absPath, $recursive);
        $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Response::toolResult([
            ['type' => 'text', 'text' => $json],
        ]);
    }
}
