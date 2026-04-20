<?php
declare(strict_types=1);

namespace App;

class Config
{
    private static array $env    = [];
    private static array $dbConf = [];
    private static bool  $loaded = false;

    private static string $envFile = '';

    public static function init(string $basePath): void
    {
        self::$envFile = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . '.env';
        self::loadEnv();
        self::$loaded = true;
    }

    // ------------------------------------------------------------------
    // ENV File helpers
    // ------------------------------------------------------------------

    private static function loadEnv(): void
    {
        if (!file_exists(self::$envFile)) return;

        $lines = file(self::$envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            self::$env[trim($key)] = trim($value);
        }
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        return self::$env[$key] ?? $default;
    }

    public static function writeEnv(array $values): bool
    {
        $existing = [];
        if (file_exists(self::$envFile)) {
            $lines = file(self::$envFile, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    $existing[] = $line;
                    continue;
                }
                if (!str_contains($line, '=')) { $existing[] = $line; continue; }
                [$k] = explode('=', $line, 2);
                $k = trim($k);
                if (!isset($values[$k])) {
                    $existing[] = $line;
                }
            }
        }

        foreach ($values as $k => $v) {
            $existing[] = "{$k}={$v}";
        }

        return (bool) file_put_contents(self::$envFile, implode("\n", $existing) . "\n");
    }

    // ------------------------------------------------------------------
    // DB Config helpers (loaded after setup)
    // ------------------------------------------------------------------

    public static function db(): array
    {
        return [
            'host'     => self::env('DB_HOST', '127.0.0.1'),
            'port'     => self::env('DB_PORT', '3306'),
            'database' => self::env('DB_DATABASE', 'googlepro'),
            'username' => self::env('DB_USERNAME', 'root'),
            'password' => self::env('DB_PASSWORD', ''),
        ];
    }

    public static function loadFromDb(\PDO $pdo): void
    {
        try {
            $rows = $pdo->query("SELECT `key`, `value` FROM `config`")->fetchAll();
            foreach ($rows as $row) {
                self::$dbConf[$row['key']] = $row['value'];
            }
        } catch (\Exception $e) {
            // Silently fail if table not yet created
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$dbConf[$key] ?? $default;
    }

    public static function set(\PDO $pdo, string $key, mixed $value): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO `config` (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $stmt->execute([$key, $value]);
        self::$dbConf[$key] = $value;
    }

    public static function isSetupComplete(): bool
    {
        return self::env('DB_DATABASE', '') !== ''
            && self::env('DB_USERNAME', '') !== ''
            && self::get('setup_complete', '0') === '1';
    }
}
