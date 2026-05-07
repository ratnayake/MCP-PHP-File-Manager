<?php

/**
 * PHP MCP File Manager — Configuration
 *
 * Copy this file to config.php and edit the values below.
 * config.php is gitignored and will not be committed.
 */

return [

    // ── Required ──────────────────────────────────────────────────────────────

    /**
     * Absolute path to the directory this server is allowed to manage.
     * All file operations are sandboxed to this directory.
     *
     * Shared hosting examples:
     *   '/home/username/public_html/uploads'
     *   '/home/username/myfiles'
     */
    'root_path' => '/home/username/public_html/files',

    /**
     * Server mode:
     *   'read_only'    — list_directory, get_file_info, read_file only
     *   'full_control' — all operations including write, delete, rename, chmod
     */
    'mode' => 'read_only',

    // ── Authentication ────────────────────────────────────────────────────────

    /**
     * Bearer token for HTTP transport authentication.
     * Set to a long random string to protect your server.
     * null = no authentication required (use only on trusted networks).
     *
     * Clients must send: Authorization: Bearer <your-token>
     * Or alternatively:  X-Auth-Token: <your-token>
     *
     * Generate a token: php -r "echo bin2hex(random_bytes(32));"
     */
    'auth_token' => null,

    // ── File Restrictions ─────────────────────────────────────────────────────

    /**
     * Whitelist of allowed file extensions (lowercase, without leading dot).
     * null = all extensions are allowed.
     *
     * Example: ['php', 'html', 'css', 'js', 'txt', 'json', 'xml', 'md']
     */
    'allowed_extensions' => null,

    /**
     * Maximum file size in bytes for read and write operations.
     * Default: 10 MB
     */
    'max_file_size' => 10 * 1024 * 1024,

    // ── Directory Listing Limits ──────────────────────────────────────────────

    /**
     * Maximum depth for recursive directory listing.
     * Default: 10 levels
     */
    'max_depth' => 10,

    /**
     * Maximum total number of entries returned in a single directory listing.
     * Default: 10,000
     */
    'max_items' => 10000,

    // ── Security ──────────────────────────────────────────────────────────────

    /**
     * Whether to follow symbolic links.
     * false (recommended) — symlinks are rejected to prevent traversal outside root_path.
     * true  — symlinks are followed; only the resolved target is checked against root_path.
     */
    'follow_symlinks' => false,

    // ── HTTP Transport ────────────────────────────────────────────────────────

    /**
     * CORS allowed origin for HTTP transport.
     * '*' = allow all origins (suitable for local/private use).
     * Set to your specific domain in production: 'https://example.com'
     */
    'cors_origin' => '*',

    // ── Server Identity ───────────────────────────────────────────────────────

    /**
     * Name and version reported in the MCP initialize response.
     */
    'server_name'    => 'php-file-manager',
    'server_version' => '1.0.0',

];
