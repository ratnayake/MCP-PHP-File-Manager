<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

use MCP\FileManager\Config\Config;
use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;
use MCP\FileManager\Protocol\Response;

class WriteFileTool implements ToolInterface
{
    private FileManager $fm;
    private PathValidator $validator;
    private int $maxFileSize;

    public function __construct(FileManager $fm, PathValidator $validator, Config $config)
    {
        $this->fm = $fm;
        $this->validator = $validator;
        $this->maxFileSize = $config->getMaxFileSize();
    }

    public function getName(): string
    {
        return 'write_file';
    }

    public function getDescription(): string
    {
        return 'Write (create or overwrite) a file with the given content. Creates any missing parent directories automatically. The content must be a UTF-8 string.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path to the file to write.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Content to write to the file (UTF-8 string).',
                ],
            ],
            'required' => ['path', 'content'],
        ];
    }

    public function execute(array $params): array
    {
        $path = $params['path'] ?? null;
        if ($path === null) {
            throw new \InvalidArgumentException('Parameter "path" is required.');
        }

        if (!isset($params['content'])) {
            throw new \InvalidArgumentException('Parameter "content" is required.');
        }

        $content = (string) $params['content'];

        // Check size before writing (use byte length, not character length)
        $size = strlen($content);
        if ($size > $this->maxFileSize) {
            throw new \RuntimeException(sprintf(
                'Content is too large to write (%d bytes). Maximum allowed size is %d bytes.',
                $size,
                $this->maxFileSize
            ));
        }

        $absPath = $this->validator->validate((string) $path, true);

        $existed = file_exists($absPath);
        $this->fm->writeFile($absPath, $content);

        $action = $existed ? 'updated' : 'created';
        return Response::toolResult([
            ['type' => 'text', 'text' => sprintf('File %s successfully: %s (%d bytes)', $action, $path, $size)],
        ]);
    }
}
