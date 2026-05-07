<?php

declare(strict_types=1);

namespace MCP\FileManager\Config;

class Config
{
    private string $rootPath;
    private string $mode;
    private ?string $authToken;
    private ?array $allowedExtensions;
    private int $maxFileSize;
    private int $maxDepth;
    private int $maxItems;
    private bool $followSymlinks;
    private string $corsOrigin;
    private string $serverName;
    private string $serverVersion;

    public function __construct(string $configFile)
    {
        if (!file_exists($configFile)) {
            throw new \RuntimeException(
                'Config file not found: ' . $configFile . '. Copy config.example.php to config.php and edit it.'
            );
        }

        $data = require $configFile;

        if (!is_array($data)) {
            throw new \RuntimeException('config.php must return an array.');
        }

        $this->rootPath = $this->resolveRootPath($data);
        $this->mode = $this->resolveMode($data);
        $this->authToken = isset($data['auth_token']) && $data['auth_token'] !== null
            ? (string) $data['auth_token']
            : null;
        $this->allowedExtensions = isset($data['allowed_extensions']) && is_array($data['allowed_extensions'])
            ? array_map('strtolower', $data['allowed_extensions'])
            : null;
        $this->maxFileSize = isset($data['max_file_size']) ? (int) $data['max_file_size'] : 10 * 1024 * 1024;
        $this->maxDepth = isset($data['max_depth']) ? (int) $data['max_depth'] : 10;
        $this->maxItems = isset($data['max_items']) ? (int) $data['max_items'] : 10000;
        $this->followSymlinks = isset($data['follow_symlinks']) ? (bool) $data['follow_symlinks'] : false;
        $this->corsOrigin = isset($data['cors_origin']) ? (string) $data['cors_origin'] : '*';
        $this->serverName = isset($data['server_name']) ? (string) $data['server_name'] : 'php-file-manager';
        $this->serverVersion = isset($data['server_version']) ? (string) $data['server_version'] : '1.0.0';
    }

    private function resolveRootPath(array $data): string
    {
        if (empty($data['root_path'])) {
            throw new \RuntimeException('config.php must define a non-empty root_path.');
        }

        $path = (string) $data['root_path'];
        $real = realpath($path);

        if ($real === false) {
            throw new \RuntimeException('root_path does not exist or is not accessible: ' . $path);
        }

        if (!is_dir($real)) {
            throw new \RuntimeException('root_path is not a directory: ' . $path);
        }

        return rtrim($real, '/\\');
    }

    private function resolveMode(array $data): string
    {
        $mode = isset($data['mode']) ? (string) $data['mode'] : 'read_only';

        if (!in_array($mode, ['read_only', 'full_control'], true)) {
            throw new \RuntimeException('config.php mode must be "read_only" or "full_control". Got: ' . $mode);
        }

        return $mode;
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isReadOnly(): bool
    {
        return $this->mode === 'read_only';
    }

    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    public function getAllowedExtensions(): ?array
    {
        return $this->allowedExtensions;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    public function getMaxItems(): int
    {
        return $this->maxItems;
    }

    public function isFollowSymlinks(): bool
    {
        return $this->followSymlinks;
    }

    public function getCorsOrigin(): string
    {
        return $this->corsOrigin;
    }

    public function getServerName(): string
    {
        return $this->serverName;
    }

    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }
}
