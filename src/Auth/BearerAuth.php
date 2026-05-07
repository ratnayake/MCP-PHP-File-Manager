<?php

declare(strict_types=1);

namespace MCP\FileManager\Auth;

class BearerAuth
{
    private string $expectedToken;

    public function __construct(string $token)
    {
        $this->expectedToken = $token;
    }

    /**
     * Validate the Bearer token from the current HTTP request.
     * Throws \RuntimeException if authentication fails.
     */
    public function authenticate(): void
    {
        $provided = $this->extractToken();

        if ($provided === null) {
            throw new \RuntimeException('Unauthorized: missing Authorization header or X-Auth-Token header.');
        }

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($this->expectedToken, $provided)) {
            throw new \RuntimeException('Unauthorized: invalid token.');
        }
    }

    private function extractToken(): ?string
    {
        // 1. Standard Authorization header (may be passed through via .htaccess RewriteRule)
        $header = $this->getAuthorizationHeader();
        if ($header !== null && strncasecmp($header, 'Bearer ', 7) === 0) {
            return trim(substr($header, 7));
        }

        // 2. X-Auth-Token header (fallback for environments that strip Authorization)
        if (isset($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            return trim($_SERVER['HTTP_X_AUTH_TOKEN']);
        }

        return null;
    }

    private function getAuthorizationHeader(): ?string
    {
        // Set by .htaccess: RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        // PHP-FPM with getallheaders() available
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            // Header names are case-insensitive per HTTP spec
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    return $value;
                }
            }
        }

        // Apache mod_rewrite alternative variable name
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return null;
    }
}
