<?php

declare(strict_types=1);

namespace MCP\FileManager\Transport;

use MCP\FileManager\Protocol\McpServer;

class StdioTransport implements TransportInterface
{
    public function run(McpServer $server): void
    {
        // Disable output buffering so responses reach the MCP host immediately
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_implicit_flush(true);

        // No execution time limit — server runs until the host closes stdin
        set_time_limit(0);

        $stdin = fopen('php://stdin', 'rb');
        if ($stdin === false) {
            fwrite(STDERR, "Failed to open stdin\n");
            exit(1);
        }

        while (!feof($stdin)) {
            $line = fgets($stdin);

            if ($line === false) {
                break;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $response = $server->handle($line);

            if ($response !== '') {
                $this->write($response);
            }
        }

        fclose($stdin);
    }

    private function write(string $json): void
    {
        fwrite(STDOUT, $json . "\n");
    }
}
