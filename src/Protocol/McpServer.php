<?php

declare(strict_types=1);

namespace MCP\FileManager\Protocol;

use MCP\FileManager\Config\Config;
use MCP\FileManager\Tools\ToolRegistry;

class McpServer
{
    private const PROTOCOL_VERSION = '2024-11-05';

    private Config $config;
    private ToolRegistry $registry;
    private bool $initialized = false;

    public function __construct(Config $config, ToolRegistry $registry)
    {
        $this->config = $config;
        $this->registry = $registry;
    }

    /**
     * Handle a single JSON-RPC request string. Returns the JSON response string.
     * For notifications (no id) returns an empty string — nothing to send back.
     * For stdio, the caller may also need to send the initialized notification separately.
     */
    public function handle(string $rawJson): string
    {
        $request = Request::fromJson($rawJson);

        if ($request->isParseError()) {
            return Response::error(null, Response::PARSE_ERROR, 'Parse error: invalid JSON.');
        }

        if ($request->isInvalid()) {
            return Response::error($request->id, Response::INVALID_REQUEST, 'Invalid Request: missing method.');
        }

        // Route by method
        switch ($request->method) {
            case 'initialize':
                return $this->handleInitialize($request);

            case 'notifications/initialized':
                // Client acknowledges our initialized notification — no response needed
                $this->initialized = true;
                return '';

            case 'ping':
                return Response::result($request->id, []);

            case 'tools/list':
                return $this->handleToolsList($request);

            case 'tools/call':
                return $this->handleToolsCall($request);

            default:
                // Unknown methods are not errors for notifications
                if ($request->isNotification()) {
                    return '';
                }
                return Response::error(
                    $request->id,
                    Response::METHOD_NOT_FOUND,
                    'Method not found: ' . $request->method
                );
        }
    }

    private function handleInitialize(Request $request): string
    {
        // Negotiate protocol version — we support only our version
        $clientVersion = $request->params['protocolVersion'] ?? self::PROTOCOL_VERSION;

        return Response::result($request->id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => [
                'tools' => new \stdClass(), // empty object signals tool support
            ],
            'serverInfo' => [
                'name'    => $this->config->getServerName(),
                'version' => $this->config->getServerVersion(),
            ],
            'instructions' => $this->buildInstructions(),
        ]);
    }

    private function buildInstructions(): string
    {
        $mode = $this->config->getMode();
        $root = $this->config->getRootPath();

        $text = "PHP File Manager MCP Server\n";
        $text .= "Mode: {$mode}\n";
        $text .= "Managed root: {$root}\n";

        if ($mode === 'read_only') {
            $text .= "Note: This server is in read-only mode. Write, delete, rename, and chmod operations are disabled.";
        } else {
            $text .= "Note: This server is in full-control mode. All file operations are enabled.";
        }

        return $text;
    }

    private function handleToolsList(Request $request): string
    {
        $tools = [];
        foreach ($this->registry->all() as $tool) {
            $tools[] = [
                'name'        => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getSchema(),
            ];
        }

        return Response::result($request->id, ['tools' => $tools]);
    }

    private function handleToolsCall(Request $request): string
    {
        $name = $request->params['name'] ?? null;
        $args = $request->params['arguments'] ?? [];

        if ($name === null || !is_string($name)) {
            return Response::error($request->id, Response::INVALID_PARAMS, 'tools/call requires a "name" parameter.');
        }

        if (!is_array($args)) {
            $args = [];
        }

        $tool = $this->registry->find($name);

        if ($tool === null) {
            return Response::error(
                $request->id,
                Response::METHOD_NOT_FOUND,
                'Unknown tool: "' . $name . '". ' .
                ($this->config->isReadOnly() && $this->registry->isWriteTool($name)
                    ? 'This tool is disabled in read_only mode.'
                    : 'Tool not found.')
            );
        }

        try {
            $result = $tool->execute($args);
            return Response::result($request->id, $result);
        } catch (\InvalidArgumentException $e) {
            return Response::error($request->id, Response::INVALID_PARAMS, $e->getMessage());
        } catch (\Throwable $e) {
            return Response::result($request->id, Response::toolError($e->getMessage()));
        }
    }

    /**
     * Returns the initialized notification string to send after responding to initialize.
     */
    public function initializedNotification(): string
    {
        return Response::notification('notifications/initialized');
    }
}
