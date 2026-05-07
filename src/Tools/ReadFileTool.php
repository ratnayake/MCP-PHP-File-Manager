<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

use MCP\FileManager\Config\Config;
use MCP\FileManager\FileSystem\BinaryDetector;
use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;
use MCP\FileManager\Protocol\Response;

class ReadFileTool implements ToolInterface
{
    private FileManager $fm;
    private PathValidator $validator;
    private BinaryDetector $detector;
    private int $maxFileSize;

    public function __construct(FileManager $fm, PathValidator $validator, BinaryDetector $detector, Config $config)
    {
        $this->fm = $fm;
        $this->validator = $validator;
        $this->detector = $detector;
        $this->maxFileSize = $config->getMaxFileSize();
    }

    public function getName(): string
    {
        return 'read_file';
    }

    public function getDescription(): string
    {
        return 'Read the contents of a file. Text files are returned as plain text. Binary files (images, archives, etc.) are returned as base64-encoded data along with MIME type and size metadata.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path to the file to read.',
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

        $absPath = $this->validator->validate((string) $path, true);

        if (!file_exists($absPath)) {
            throw new \RuntimeException('File not found: ' . $path);
        }

        if (is_dir($absPath)) {
            throw new \RuntimeException('Path is a directory, not a file: ' . $path);
        }

        $size = filesize($absPath);
        if ($size === false) {
            throw new \RuntimeException('Cannot determine file size: ' . $path);
        }

        if ($size > $this->maxFileSize) {
            throw new \RuntimeException(sprintf(
                'File is too large to read: %s (%s). Maximum allowed size is %s.',
                $path,
                $this->formatBytes($size),
                $this->formatBytes($this->maxFileSize)
            ));
        }

        if ($this->detector->isBinary($absPath)) {
            return $this->readBinary($absPath, $path, $size);
        }

        $content = $this->fm->readFile($absPath);

        return Response::toolResult([
            ['type' => 'text', 'text' => $content],
        ]);
    }

    private function readBinary(string $absPath, string $relPath, int $size): array
    {
        $mime = $this->detector->guessMimeType($absPath);
        $raw = $this->fm->readFile($absPath);
        $encoded = base64_encode($raw);

        $meta = sprintf(
            "Binary file: %s\nMIME type: %s\nSize: %s",
            $relPath,
            $mime,
            $this->formatBytes($size)
        );

        return Response::toolResult([
            ['type' => 'text', 'text' => $meta],
            ['type' => 'text', 'text' => 'base64:' . $encoded],
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
