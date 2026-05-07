<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;
use MCP\FileManager\Protocol\Response;

class CreateDirectoryTool implements ToolInterface
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
        return 'create_directory';
    }

    public function getDescription(): string
    {
        return 'Create a new directory (and any missing parent directories) at the given path.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path of the directory to create.',
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
        $this->fm->createDirectory($absPath);

        return Response::toolResult([
            ['type' => 'text', 'text' => 'Directory created successfully: ' . $path],
        ]);
    }
}
