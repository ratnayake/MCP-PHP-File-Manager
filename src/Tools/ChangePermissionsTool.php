<?php

declare(strict_types=1);

namespace MCP\FileManager\Tools;

use MCP\FileManager\FileSystem\FileManager;
use MCP\FileManager\FileSystem\PathValidator;
use MCP\FileManager\Protocol\Response;

class ChangePermissionsTool implements ToolInterface
{
    private FileManager $fm;
    private PathValidator $validator;

    public function __construct(FileManager $fm, PathValidator $validator)
    {
        $this->fm = $fm;
        $this->validator = $validator;
    }

    public function getName(): string
    {
        return 'change_permissions';
    }

    public function getDescription(): string
    {
        return 'Change the permissions (chmod) of a file or directory. Provide the octal mode as a string (e.g. "755" or "644") or as an integer. Note: on some shared hosting environments with suEXEC, chmod may have no effect.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path to the file or directory.',
                ],
                'mode' => [
                    'type' => 'string',
                    'description' => 'Octal permission mode as a string, e.g. "755", "644", "777", "400".',
                    'pattern' => '^[0-7]{3,4}$',
                ],
            ],
            'required' => ['path', 'mode'],
        ];
    }

    public function execute(array $params): array
    {
        $path = $params['path'] ?? null;
        $modeInput = $params['mode'] ?? null;

        if ($path === null) {
            throw new \InvalidArgumentException('Parameter "path" is required.');
        }

        if ($modeInput === null) {
            throw new \InvalidArgumentException('Parameter "mode" is required.');
        }

        // Accept mode as string ("755") or integer (755 decimal or 0755 octal)
        $modeStr = ltrim((string) $modeInput, '0');
        if ($modeStr === '') {
            $modeStr = '0';
        }

        // Validate the string is a valid octal pattern (3-4 octal digits)
        if (!preg_match('/^[0-7]{1,4}$/', (string) $modeInput)) {
            throw new \InvalidArgumentException(
                'Invalid mode "' . $modeInput . '". Must be an octal string like "755" or "644".'
            );
        }

        // Convert the octal string to an integer
        $mode = octdec((string) $modeInput);

        if ($mode < 0 || $mode > 0777) {
            throw new \InvalidArgumentException(
                'Mode must be between 000 and 777 (octal). Got: ' . $modeInput
            );
        }

        $absPath = $this->validator->validate((string) $path);
        $this->fm->chmod($absPath, $mode);

        return Response::toolResult([
            ['type' => 'text', 'text' => sprintf(
                'Permissions changed to %s on: %s',
                $modeInput,
                $path
            )],
        ]);
    }
}
