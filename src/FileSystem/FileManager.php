<?php

declare(strict_types=1);

namespace MCP\FileManager\FileSystem;

use MCP\FileManager\Config\Config;

class FileManager
{
    private string $root;
    private int $maxDepth;
    private int $maxItems;

    public function __construct(Config $config)
    {
        $this->root = $config->getRootPath();
        $this->maxDepth = $config->getMaxDepth();
        $this->maxItems = $config->getMaxItems();
    }

    /**
     * List directory contents. Returns array of entry arrays.
     * Each entry: [name, path (relative), type, size, permissions, modified, is_symlink]
     */
    public function listDirectory(string $absPath, bool $recursive, int $depth = 0): array
    {
        if ($depth > $this->maxDepth) {
            return [];
        }

        $entries = scandir($absPath);
        if ($entries === false) {
            throw new \RuntimeException('Cannot read directory: ' . $this->toRelative($absPath));
        }

        $results = [];
        $count = 0;

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            if (++$count > $this->maxItems) {
                break;
            }

            $fullPath = $absPath . DIRECTORY_SEPARATOR . $name;
            $isSymlink = is_link($fullPath);
            $isDir = is_dir($fullPath);

            $stat = @stat($fullPath);
            $perms = $stat !== false ? $stat['mode'] : 0;
            $size = ($stat !== false && !$isDir) ? $stat['size'] : null;
            $modified = $stat !== false ? $stat['mtime'] : null;

            $entry = [
                'name'        => $name,
                'path'        => $this->toRelative($fullPath),
                'type'        => $isDir ? 'directory' : 'file',
                'size'        => $size,
                'permissions' => $this->formatPermissions($perms),
                'permissions_octal' => sprintf('%04o', $perms & 0777),
                'modified'    => $modified !== null ? date('Y-m-d H:i:s', $modified) : null,
                'is_symlink'  => $isSymlink,
            ];

            if ($recursive && $isDir && !$isSymlink) {
                $entry['children'] = $this->listDirectory($fullPath, true, $depth + 1);
            }

            $results[] = $entry;
        }

        return $results;
    }

    /**
     * Return stat information for a single path.
     */
    public function stat(string $absPath): array
    {
        $exists = file_exists($absPath);
        $isLink = is_link($absPath);
        $isDir = is_dir($absPath);
        $stat = $exists ? @stat($absPath) : false;

        return [
            'path'        => $this->toRelative($absPath),
            'exists'      => $exists,
            'type'        => !$exists ? 'not_found' : ($isDir ? 'directory' : 'file'),
            'size'        => ($stat !== false && !$isDir) ? $stat['size'] : null,
            'permissions' => $stat !== false ? $this->formatPermissions($stat['mode']) : null,
            'permissions_octal' => $stat !== false ? sprintf('%04o', $stat['mode'] & 0777) : null,
            'modified'    => ($stat !== false && isset($stat['mtime'])) ? date('Y-m-d H:i:s', $stat['mtime']) : null,
            'created'     => ($stat !== false && isset($stat['ctime'])) ? date('Y-m-d H:i:s', $stat['ctime']) : null,
            'is_symlink'  => $isLink,
            'is_readable' => is_readable($absPath),
            'is_writable' => is_writable($absPath),
        ];
    }

    /**
     * Read file contents as a string. Caller must enforce max_file_size before calling.
     */
    public function readFile(string $absPath): string
    {
        $content = file_get_contents($absPath);
        if ($content === false) {
            throw new \RuntimeException('Cannot read file: ' . $this->toRelative($absPath));
        }
        return $content;
    }

    /**
     * Write content to a file. Creates parent directories if needed.
     */
    public function writeFile(string $absPath, string $content): void
    {
        $dir = dirname($absPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException('Cannot create parent directory for: ' . $this->toRelative($absPath));
            }
        }

        $result = file_put_contents($absPath, $content, LOCK_EX);
        if ($result === false) {
            throw new \RuntimeException('Cannot write file: ' . $this->toRelative($absPath));
        }
    }

    /**
     * Delete a file or recursively delete a directory.
     */
    public function deletePath(string $absPath): void
    {
        if (is_dir($absPath) && !is_link($absPath)) {
            $this->deleteDirectory($absPath);
        } else {
            if (!unlink($absPath)) {
                throw new \RuntimeException('Cannot delete: ' . $this->toRelative($absPath));
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        $entries = scandir($dir);
        if ($entries === false) {
            throw new \RuntimeException('Cannot read directory for deletion: ' . $this->toRelative($dir));
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->deleteDirectory($path);
            } else {
                if (!unlink($path)) {
                    throw new \RuntimeException('Cannot delete file: ' . $this->toRelative($path));
                }
            }
        }

        if (!rmdir($dir)) {
            throw new \RuntimeException('Cannot remove directory: ' . $this->toRelative($dir));
        }
    }

    /**
     * Rename / move a path. $newAbsPath must already be validated.
     */
    public function renamePath(string $fromAbs, string $toAbs): void
    {
        if (file_exists($toAbs)) {
            throw new \RuntimeException(
                'Destination already exists: ' . $this->toRelative($toAbs)
            );
        }

        if (!rename($fromAbs, $toAbs)) {
            throw new \RuntimeException(
                'Cannot rename "' . $this->toRelative($fromAbs) . '" to "' . $this->toRelative($toAbs) . '".'
            );
        }
    }

    /**
     * Change permissions. $mode is an integer (e.g. 0755).
     */
    public function chmod(string $absPath, int $mode): void
    {
        if (!file_exists($absPath) && !is_link($absPath)) {
            throw new \RuntimeException('Path does not exist: ' . $this->toRelative($absPath));
        }

        if (!chmod($absPath, $mode)) {
            throw new \RuntimeException(
                'Cannot change permissions on: ' . $this->toRelative($absPath) .
                '. This may be restricted on your hosting environment.'
            );
        }
    }

    /**
     * Create a directory (and any missing parents).
     */
    public function createDirectory(string $absPath): void
    {
        if (file_exists($absPath)) {
            throw new \RuntimeException('Path already exists: ' . $this->toRelative($absPath));
        }

        if (!mkdir($absPath, 0755, true)) {
            throw new \RuntimeException('Cannot create directory: ' . $this->toRelative($absPath));
        }
    }

    private function toRelative(string $absPath): string
    {
        if (strncmp($absPath, $this->root, strlen($this->root)) === 0) {
            $rel = substr($absPath, strlen($this->root));
            return ltrim(str_replace('\\', '/', $rel), '/') ?: '/';
        }
        return $absPath;
    }

    private function formatPermissions(int $mode): string
    {
        $types = [
            0140000 => 's', // socket
            0120000 => 'l', // symlink
            0100000 => '-', // regular file
            0060000 => 'b', // block device
            0040000 => 'd', // directory
            0020000 => 'c', // char device
            0010000 => 'p', // FIFO
        ];

        $type = '-';
        foreach ($types as $mask => $char) {
            if (($mode & 0170000) === $mask) {
                $type = $char;
                break;
            }
        }

        $perms = $type;
        $perms .= (($mode & 0400) ? 'r' : '-');
        $perms .= (($mode & 0200) ? 'w' : '-');
        $perms .= (($mode & 0100) ? (($mode & 04000) ? 's' : 'x') : (($mode & 04000) ? 'S' : '-'));
        $perms .= (($mode & 0040) ? 'r' : '-');
        $perms .= (($mode & 0020) ? 'w' : '-');
        $perms .= (($mode & 0010) ? (($mode & 02000) ? 's' : 'x') : (($mode & 02000) ? 'S' : '-'));
        $perms .= (($mode & 0004) ? 'r' : '-');
        $perms .= (($mode & 0002) ? 'w' : '-');
        $perms .= (($mode & 0001) ? (($mode & 01000) ? 't' : 'x') : (($mode & 01000) ? 'T' : '-'));

        return $perms;
    }
}
