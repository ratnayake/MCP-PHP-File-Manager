<?php

declare(strict_types=1);

namespace MCP\FileManager\FileSystem;

class BinaryDetector
{
    private static array $binaryExtensions = [
        'png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp', 'ico', 'svg',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'tar', 'gz', 'bz2', 'xz', 'rar', '7z',
        'mp3', 'mp4', 'avi', 'mov', 'mkv', 'flac', 'wav', 'ogg',
        'exe', 'dll', 'so', 'dylib', 'bin', 'dat', 'iso',
        'ttf', 'otf', 'woff', 'woff2', 'eot',
        'swf', 'fla',
    ];

    /**
     * Returns true if the file appears to be binary content.
     */
    public function isBinary(string $filePath): bool
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, self::$binaryExtensions, true)) {
            return true;
        }

        // Read first 8 KB and look for non-printable bytes
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $chunk = fread($handle, 8192);
        fclose($handle);

        if ($chunk === false || $chunk === '') {
            return false;
        }

        // Check for bytes outside printable ASCII + common whitespace
        // (tab, LF, CR, and 0x20–0x7E are text; anything else is binary)
        return preg_match('/[^\x09\x0A\x0D\x20-\x7E]/u', $chunk) === 1;
    }

    /**
     * Guess MIME type from extension (no system dependency).
     */
    public function guessMimeType(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $map = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp',
            'ico' => 'image/x-icon', 'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip', 'tar' => 'application/x-tar',
            'gz' => 'application/gzip', 'bz2' => 'application/x-bzip2',
            'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4',
            'txt' => 'text/plain', 'html' => 'text/html', 'htm' => 'text/html',
            'css' => 'text/css', 'js' => 'application/javascript',
            'json' => 'application/json', 'xml' => 'application/xml',
            'php' => 'application/x-httpd-php',
            'exe' => 'application/octet-stream',
            'ttf' => 'font/ttf', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }
}
