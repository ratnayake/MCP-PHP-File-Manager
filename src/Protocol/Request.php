<?php

declare(strict_types=1);

namespace MCP\FileManager\Protocol;

class Request
{
    public ?string $jsonrpc;
    /** @var string|int|null */
    public $id;
    public string $method;
    public array $params;

    private function __construct()
    {
    }

    /**
     * Parse a raw JSON string into a Request.
     * Returns null on parse error (caller should send -32700).
     * Returns a Request with method='__invalid__' on validation failure (caller sends -32600).
     */
    public static function fromJson(string $raw): self
    {
        $obj = self::new();

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $obj->method = '__parse_error__';
            return $obj;
        }

        if (!isset($decoded['method']) || !is_string($decoded['method'])) {
            $obj->jsonrpc = $decoded['jsonrpc'] ?? null;
            $obj->id = $decoded['id'] ?? null;
            $obj->method = '__invalid__';
            $obj->params = [];
            return $obj;
        }

        $obj->jsonrpc = $decoded['jsonrpc'] ?? null;
        $obj->id = $decoded['id'] ?? null;
        $obj->method = $decoded['method'];
        $obj->params = isset($decoded['params']) && is_array($decoded['params'])
            ? $decoded['params']
            : [];

        return $obj;
    }

    private static function new(): self
    {
        $r = new self();
        $r->jsonrpc = null;
        $r->id = null;
        $r->method = '';
        $r->params = [];
        return $r;
    }

    public function isParseError(): bool
    {
        return $this->method === '__parse_error__';
    }

    public function isInvalid(): bool
    {
        return $this->method === '__invalid__';
    }

    public function isNotification(): bool
    {
        return $this->id === null && !$this->isParseError() && !$this->isInvalid();
    }
}
