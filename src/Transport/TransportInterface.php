<?php

declare(strict_types=1);

namespace MCP\FileManager\Transport;

use MCP\FileManager\Protocol\McpServer;

interface TransportInterface
{
    /**
     * Start the transport loop. Reads requests and writes responses until done.
     */
    public function run(McpServer $server): void;
}
