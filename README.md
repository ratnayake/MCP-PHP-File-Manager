# MCP PHP File Manager

An MCP (Model Context Protocol) server written in PHP that lets AI assistants manage files on shared hosting servers. Works over **stdio** (for Claude Desktop and similar MCP hosts) and **HTTP** (for deployment on shared hosting via a web request).

## Features

- List files and directories (with metadata: size, permissions, modified date)
- View file contents — text files as plain text, binary files as base64
- Create and edit files
- Delete files and directories (recursive)
- Rename / move files and directories
- Change file permissions (chmod)
- Create directories

### Two operating modes

| Mode | Available tools |
|---|---|
| `read_only` | list_directory, get_file_info, read_file |
| `full_control` | all of the above + write_file, delete_path, rename_path, change_permissions, create_directory |

The mode is set in `config.php` and cannot be changed at runtime.

## Requirements

- PHP 7.4 or higher
- No database required
- No required Composer packages (Composer is optional — used only for autoloading)

## Installation

### 1. Copy and configure

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
return [
    'root_path' => '/home/username/public_html/files',  // directory to manage
    'mode'      => 'read_only',                          // or 'full_control'
    'auth_token' => null,                                // set a token for HTTP auth
];
```

### 2a. Claude Desktop (stdio mode)

Add to your `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "file-manager": {
      "command": "php",
      "args": ["/absolute/path/to/server.php"]
    }
  }
}
```

Restart Claude Desktop. The file manager tools will appear automatically.

### 2b. Shared hosting (HTTP mode)

Upload all files to your hosting account (e.g. a subdirectory of `public_html`):

```
public_html/
└── mcp-files/
    ├── server.php
    ├── config.php
    ├── .htaccess
    └── src/
```

The server is now reachable at `https://yourdomain.com/mcp-files/server.php`.

Set an `auth_token` in `config.php` before exposing it to the internet.

#### Connect from an MCP client via HTTP

```json
{
  "mcpServers": {
    "file-manager": {
      "url": "https://yourdomain.com/mcp-files/server.php",
      "headers": {
        "Authorization": "Bearer your-secret-token"
      }
    }
  }
}
```

### Optional: Composer autoloading

If you have Composer available:

```bash
composer install
```

`server.php` detects `vendor/autoload.php` automatically and uses it. Without Composer it falls back to manual `require_once` — no functionality difference.

## Security

- **All paths are sandboxed** to `root_path`. Path traversal attempts (`../../etc/passwd`) are blocked.
- **Symlinks** are rejected by default (`follow_symlinks: false`).
- **Authentication** via Bearer token (`auth_token` in config) — uses timing-safe `hash_equals()` comparison.
- **Read-only mode** disables all write, delete, rename, and chmod tools at the registry level.
- **`.htaccess`** blocks direct browser access to `src/` and `config.php`.

### Generate a secure token

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

### Apache Authorization header

Some cPanel/Apache setups strip the `Authorization` header. The included `.htaccess` adds:

```apache
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

Alternatively send the token as `X-Auth-Token: your-token`.

## MCP Tools Reference

### `list_directory`
List the contents of a directory.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `path` | string | yes | Relative path (use `"/"` for root) |
| `recursive` | boolean | no | List subdirectories recursively (default: false) |

### `get_file_info`
Get detailed info about a file or directory (size, permissions, dates).

| Parameter | Type | Required | Description |
|---|---|---|---|
| `path` | string | yes | Relative path |

### `read_file`
Read file contents. Binary files are returned as base64.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `path` | string | yes | Relative path to the file |

### `write_file` *(full_control only)*
Create or overwrite a file.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `path` | string | yes | Relative path |
| `content` | string | yes | File content (UTF-8) |

### `delete_path` *(full_control only)*
Delete a file or directory (directories are deleted recursively).

| Parameter | Type | Required | Description |
|---|---|---|---|
| `path` | string | yes | Relative path |

### `rename_path` *(full_control only)*
Rename or move a file or directory.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `path` | string | yes | Current relative path |
| `new_path` | string | yes | New relative path |

### `change_permissions` *(full_control only)*
Change file or directory permissions (chmod).

| Parameter | Type | Required | Description |
|---|---|---|---|
| `path` | string | yes | Relative path |
| `mode` | string | yes | Octal permission string, e.g. `"755"`, `"644"` |

### `create_directory` *(full_control only)*
Create a directory (and any missing parents).

| Parameter | Type | Required | Description |
|---|---|---|---|
| `path` | string | yes | Relative path of directory to create |

## Configuration Reference

| Key | Type | Default | Description |
|---|---|---|---|
| `root_path` | string | — | **Required.** Absolute path to managed directory |
| `mode` | string | `read_only` | `read_only` or `full_control` |
| `auth_token` | string\|null | `null` | Bearer token for HTTP auth; `null` = no auth |
| `allowed_extensions` | array\|null | `null` | File extension whitelist; `null` = all allowed |
| `max_file_size` | int | `10485760` | Max file size in bytes for read/write (10 MB) |
| `max_depth` | int | `10` | Max depth for recursive directory listing |
| `max_items` | int | `10000` | Max entries in a single listing |
| `follow_symlinks` | bool | `false` | Whether to follow symbolic links |
| `cors_origin` | string | `*` | CORS `Access-Control-Allow-Origin` value |
| `server_name` | string | `php-file-manager` | Name reported in MCP initialize |
| `server_version` | string | `1.0.0` | Version reported in MCP initialize |

## Shared Hosting Notes

- **`chmod`** may have no effect on hosts using suEXEC — the server reports this clearly rather than silently failing.
- **`open_basedir`** restrictions: ensure `root_path` is within your hosting account's allowed paths.
- The server never calls `exec`, `shell_exec`, or `proc_open` — only native PHP filesystem functions.
- File writes use `LOCK_EX` for basic concurrency safety.

## License

GNU General Public License v3.0 — see [LICENSE](LICENSE).
