<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * Returns a JSON Schema object describing the tool's input parameters.
     */
    public function getSchema(): array;

    /**
     * Execute the tool with the given arguments.
     * Returns an MCP toolResult array (content[], isError).
     * Throw \InvalidArgumentException for bad params (caller maps to -32602).
     * Throw \RuntimeException for execution failures (caller maps to isError: true).
     */
    public function execute(array $params): array;
}
