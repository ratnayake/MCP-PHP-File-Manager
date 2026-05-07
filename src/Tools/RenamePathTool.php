<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;
use MCP\FileManager\Protocol\Response;

class RenamePathTool implements ToolInterface
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
        return 'rename_path';
    }

    public function getDescription(): string
    {
        return 'Rename or move a file or directory. Provide the current relative path and the new relative path (which can be in a different directory to effectively move it). Both paths must be within the managed root.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Current relative path of the file or directory.',
                ],
                'new_path' => [
                    'type' => 'string',
                    'description' => 'New relative path (rename) or destination path (move).',
                ],
            ],
            'required' => ['path', 'new_path'],
        ];
    }

    public function execute(array $params): array
    {
        $path = $params['path'] ?? null;
        $newPath = $params['new_path'] ?? null;

        if ($path === null) {
            throw new \InvalidArgumentException('Parameter "path" is required.');
        }

        if ($newPath === null) {
            throw new \InvalidArgumentException('Parameter "new_path" is required.');
        }

        $fromAbs = $this->validator->validate((string) $path);
        $toAbs = $this->validator->validate((string) $newPath);

        if (!file_exists($fromAbs) && !is_link($fromAbs)) {
            throw new \RuntimeException('Source path does not exist: ' . $path);
        }

        if ($fromAbs === $this->validator->getRoot()) {
            throw new \RuntimeException('Cannot rename the root directory.');
        }

        $this->fm->renamePath($fromAbs, $toAbs);

        return Response::toolResult([
            ['type' => 'text', 'text' => sprintf('Successfully renamed "%s" to "%s"', $path, $newPath)],
        ]);
    }
}
