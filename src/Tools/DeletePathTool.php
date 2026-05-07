<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;
use MCP\FileManager\Protocol\Response;

class DeletePathTool implements ToolInterface
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
        return 'delete_path';
    }

    public function getDescription(): string
    {
        return 'Delete a file or directory. Directories are deleted recursively along with all their contents. This action cannot be undone — use with caution.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path to the file or directory to delete.',
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

        // Prevent deleting the root itself
        if ($absPath === $this->validator->getRoot()) {
            throw new \RuntimeException('Cannot delete the root directory.');
        }

        if (!file_exists($absPath) && !is_link($absPath)) {
            throw new \RuntimeException('Path does not exist: ' . $path);
        }

        $type = is_dir($absPath) && !is_link($absPath) ? 'directory' : 'file';
        $this->fm->deletePath($absPath);

        return Response::toolResult([
            ['type' => 'text', 'text' => sprintf('Successfully deleted %s: %s', $type, $path)],
        ]);
    }
}
