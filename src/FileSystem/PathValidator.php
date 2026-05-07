<?php

declare(strict_types=1);

namespace MCP\FileManager\FileSystem;

use MCP\FileManager\Config\Config;

class PathValidator
{
    private string $root;
    private bool $followSymlinks;
    private ?array $allowedExtensions;

    public function __construct(Config $config)
    {
        $this->root = $config->getRootPath();
        $this->followSymlinks = $config->isFollowSymlinks();
        $this->allowedExtensions = $config->getAllowedExtensions();
    }

    /**
     * Validate and resolve a user-supplied relative path.
     * Returns the absolute path inside root_path.
     * Throws \RuntimeException on any traversal or policy violation.
     */
    public function validate(string $input, bool $checkExtension = false): string
    {
        // Reject null bytes — can truncate path in C layer on some platforms
        if (strpos($input, "\0") !== false) {
            throw new \RuntimeException('Invalid path: null bytes are not allowed.');
        }

        // Normalise separators and strip leading slashes so the path is relative
        $normalised = str_replace('\\', '/', $input);
        $normalised = ltrim($normalised, '/');

        // Build the candidate absolute path
        $candidate = $this->root . '/' . $normalised;

        // Try realpath first (works for existing paths)
        $resolved = realpath($candidate);

        if ($resolved === false) {
            // Path does not exist yet (e.g. new file/directory to be created)
            // Resolve manually to handle ".." segments without touching the filesystem
            $resolved = $this->resolvePath($candidate);
        }

        // Security: ensure the resolved path is inside root
        $this->assertInsideRoot($resolved);

        // Security: reject symlinks if not configured to follow them
        if (!$this->followSymlinks && file_exists($resolved) && is_link($resolved)) {
            throw new \RuntimeException('Symlinks are not allowed: ' . $input);
        }

        // Extension whitelist check (for file operations only)
        if ($checkExtension && $this->allowedExtensions !== null) {
            $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedExtensions, true)) {
                throw new \RuntimeException(
                    'File extension "' . $ext . '" is not in the allowed extensions list.'
                );
            }
        }

        return $resolved;
    }

    /**
     * Resolve ".." and "." segments without calling realpath (for non-existent paths).
     */
    private function resolvePath(string $path): string
    {
        $parts = explode('/', str_replace('\\', '/', $path));
        $resolved = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (!empty($resolved)) {
                    array_pop($resolved);
                }
            } else {
                $resolved[] = $part;
            }
        }

        // On Windows the first part may be a drive letter (e.g. "C:")
        // On Unix we need to restore the leading slash
        $result = implode('/', $resolved);

        if (PHP_OS_FAMILY !== 'Windows') {
            $result = '/' . $result;
        }

        return $result;
    }

    private function assertInsideRoot(string $resolved): void
    {
        // Exact match (the root itself)
        if ($resolved === $this->root) {
            return;
        }

        // Must start with root + directory separator
        $prefix = $this->root . DIRECTORY_SEPARATOR;
        if (strncmp($resolved, $prefix, strlen($prefix)) !== 0) {
            throw new \RuntimeException('Access denied: path is outside the managed root directory.');
        }
    }

    public function getRoot(): string
    {
        return $this->root;
    }
}
