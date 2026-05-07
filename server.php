<?php

declare(strict_types=1);

/**
 * PHP MCP File Manager — Entry Point
 *
 * Transport detection:
 *   CLI (php server.php)  → StdioTransport (for MCP hosts like Claude Desktop)
 *   HTTP (web request)    → HttpTransport  (for shared hosting)
 *
 * Setup:
 *   1. Copy config.example.php to config.php and edit it.
 *   2. Run: php server.php  (CLI)
 *      OR deploy to a web server and POST to server.php (HTTP).
 */

// ── Autoloading ───────────────────────────────────────────────────────────────

// Guard: catch missing src/ directory before PHP emits a cryptic fatal error
if (!is_dir(__DIR__ . '/src')) {
    $isCli = PHP_SAPI === 'cli';
    $msg   = 'Setup error: the src/ directory is missing next to server.php. '
           . 'Please upload the complete repository including the src/ folder. '
           . 'See README.md for installation instructions.';
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'jsonrpc' => '2.0',
            'id'      => null,
            'error'   => ['code' => -32603, 'message' => $msg],
        ]);
    }
    exit(1);
}

// Try Composer autoloader first, but only if it actually knows about our classes.
// A vendor/autoload.php from a different project or an empty `composer install`
// will not register MCP\FileManager — in that case fall back to manual requires.
$composerLoaded = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $composerLoaded = class_exists('MCP\\FileManager\\Config\\Config', true);
}

if (!$composerLoaded) {
    // Manual requires — no external dependencies needed
    require_once __DIR__ . '/src/Config/Config.php';
    require_once __DIR__ . '/src/FileSystem/PathValidator.php';
    require_once __DIR__ . '/src/FileSystem/BinaryDetector.php';
    require_once __DIR__ . '/src/FileSystem/FileManager.php';
    require_once __DIR__ . '/src/Auth/BearerAuth.php';
    require_once __DIR__ . '/src/Protocol/Request.php';
    require_once __DIR__ . '/src/Protocol/Response.php';
    require_once __DIR__ . '/src/Tools/ToolInterface.php';
    require_once __DIR__ . '/src/Tools/ListDirectoryTool.php';
    require_once __DIR__ . '/src/Tools/GetFileInfoTool.php';
    require_once __DIR__ . '/src/Tools/ReadFileTool.php';
    require_once __DIR__ . '/src/Tools/WriteFileTool.php';
    require_once __DIR__ . '/src/Tools/DeletePathTool.php';
    require_once __DIR__ . '/src/Tools/RenamePathTool.php';
    require_once __DIR__ . '/src/Tools/ChangePermissionsTool.php';
    require_once __DIR__ . '/src/Tools/CreateDirectoryTool.php';
    require_once __DIR__ . '/src/Tools/ToolRegistry.php';
    require_once __DIR__ . '/src/Transport/TransportInterface.php';
    require_once __DIR__ . '/src/Transport/StdioTransport.php';
    require_once __DIR__ . '/src/Transport/HttpTransport.php';
    require_once __DIR__ . '/src/Protocol/McpServer.php';
}

use MCP\FileManager\Config\Config;
use MCP\FileManager\FileSystem\BinaryDetector;
use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;
use MCP\FileManager\Protocol\McpServer;
use MCP\FileManager\Protocol\Response;
use MCP\FileManager\Tools\ToolRegistry;
use MCP\FileManager\Transport\HttpTransport;
use MCP\FileManager\Transport\StdioTransport;

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$isCli = PHP_SAPI === 'cli';

try {
    $config = new Config(__DIR__ . '/config.php');
} catch (\Throwable $e) {
    $msg = 'Configuration error: ' . $e->getMessage();
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo Response::error(null, Response::INTERNAL_ERROR, $msg);
        exit;
    }
}

$validator = new PathValidator($config);
$detector  = new BinaryDetector();
$fm        = new FileManager($config);
$registry  = new ToolRegistry($config, $fm, $validator, $detector);
$server    = new McpServer($config, $registry);

// ── Run ───────────────────────────────────────────────────────────────────────
if ($isCli) {
    (new StdioTransport())->run($server);
} else {
    (new HttpTransport($config))->run($server);
}
