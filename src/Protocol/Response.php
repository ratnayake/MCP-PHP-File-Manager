<?php

declare(strict_types=1);

namespace MCP\FileManager\Protocol;

class Response
{
    // Standard JSON-RPC 2.0 error codes
    const PARSE_ERROR      = -32700;
    const INVALID_REQUEST  = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS   = -32602;
    const INTERNAL_ERROR   = -32603;

    // Custom codes
    const UNAUTHORIZED     = -32001;

    /**
     * Successful result response.
     * @param string|int|null $id
     * @param mixed $result
     */
    public static function result($id, $result): string
    {
        return self::encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ]);
    }

    /**
     * JSON-RPC error response.
     * @param string|int|null $id
     */
    public static function error($id, int $code, string $message): string
    {
        return self::encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ]);
    }

    /**
     * Notification (no id, no reply expected from client).
     */
    public static function notification(string $method, array $params = []): string
    {
        return self::encode([
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
        ]);
    }

    /**
     * Build a successful MCP tools/call content array.
     * @param array $contentItems  Each item: ['type' => 'text', 'text' => '...']
     */
    public static function toolResult(array $contentItems, bool $isError = false): array
    {
        return [
            'content' => $contentItems,
            'isError' => $isError,
        ];
    }

    /**
     * Build a tool error content array (isError = true).
     */
    public static function toolError(string $message): array
    {
        return self::toolResult([['type' => 'text', 'text' => $message]], true);
    }

    private static function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
