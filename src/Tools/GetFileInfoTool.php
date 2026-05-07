<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;
use MCP\FileManager\Protocol\Response;

class GetFileInfoTool implements ToolInterface
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
        return 'get_file_info';
    }

    public function getDescription(): string
    {
        return 'Get detailed information about a file or directory: type, size, permissions (symbolic and octal), modification time, whether it is readable/writable, and whether it is a symlink.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path to the file or directory.',
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

        $absPath = $this->validator->validate((string) $path);
        $info = $this->fm->stat($absPath);

        $json = json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Response::toolResult([
            ['type' => 'text', 'text' => $json],
        ]);
    }
}
