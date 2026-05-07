<?php

declare(strict_types=1);

namespace MCP\FileManager\Transport;

use MCP\FileManager\Auth\BearerAuth;
use MCP\FileManager\Config\Config;
use MCP\FileManager\Protocol\McpServer;
use MCP\FileManager\Protocol\Response;

class HttpTransport implements TransportInterface
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function run(McpServer $server): void
    {
        $this->sendCorsHeaders();

        // Handle CORS preflight
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Only accept POST
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST, OPTIONS');
            $this->sendJson(Response::error(null, Response::INVALID_REQUEST, 'Method Not Allowed. Use POST.'));
            exit;
        }

        // Authenticate if a token is configured
        $token = $this->config->getAuthToken();
        if ($token !== null) {
            try {
                (new BearerAuth($token))->authenticate();
            } catch (\RuntimeException $e) {
                http_response_code(401);
                $this->sendJson(Response::error(null, Response::UNAUTHORIZED, $e->getMessage()));
                exit;
            }
        }

        // Read the request body
        $body = file_get_contents('php://input');
        if ($body === false || trim($body) === '') {
            http_response_code(400);
            $this->sendJson(Response::error(null, Response::PARSE_ERROR, 'Empty request body.'));
            exit;
        }

        $response = $server->handle($body);

        if ($response === '') {
            // Notification — nothing to return
            http_response_code(202);
            exit;
        }

        $this->sendJson($response);
    }

    private function sendCorsHeaders(): void
    {
        $origin = $this->config->getCorsOrigin();
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
        header('Access-Control-Max-Age: 86400');
    }

    private function sendJson(string $json): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
    }
}
