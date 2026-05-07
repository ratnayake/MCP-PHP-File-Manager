<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

use MCP\FileManager\Config\Config;
use MCP\FileManager\FileSystem\BinaryDetector;
use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;

class ToolRegistry
{
    /** @var ToolInterface[] */
    private array $tools = [];

    /** Tools that require full_control mode */
    private static array $writeToolNames = [
        'write_file',
        'delete_path',
        'rename_path',
        'change_permissions',
        'create_directory',
    ];

    public function __construct(Config $config, FileManager $fm, PathValidator $validator, BinaryDetector $detector)
    {
        // Read-only tools — always registered
        $this->register(new ListDirectoryTool($fm, $validator));
        $this->register(new GetFileInfoTool($fm, $validator));
        $this->register(new ReadFileTool($fm, $validator, $detector, $config));

        // Write tools — only in full_control mode
        if (!$config->isReadOnly()) {
            $this->register(new WriteFileTool($fm, $validator, $config));
            $this->register(new DeletePathTool($fm, $validator));
            $this->register(new RenamePathTool($fm, $validator));
            $this->register(new ChangePermissionsTool($fm, $validator));
            $this->register(new CreateDirectoryTool($fm, $validator));
        }
    }

    private function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /** @return ToolInterface[] */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function find(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Check if a tool name belongs to the write-only category.
     * Used to provide a helpful error message when called in read_only mode.
     */
    public function isWriteTool(string $name): bool
    {
        return in_array($name, self::$writeToolNames, true);
    }
}
