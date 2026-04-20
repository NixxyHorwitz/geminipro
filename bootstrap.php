<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('SRC_PATH',  BASE_PATH . '/src');
define('BOT_PATH',  BASE_PATH . '/bot');

// Simple PSR-4 style autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = SRC_PATH . DIRECTORY_SEPARATOR . $relative . '.php';
    if (file_exists($file)) require_once $file;
});

// Manual includes for bot
function loadBot(string $file): void {
    $path = BOT_PATH . DIRECTORY_SEPARATOR . $file;
    if (file_exists($path)) require_once $path;
}

// Initialize config
\App\Config::init(BASE_PATH);

// Session start (if not CLI)
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Connect DB & load DB config (if setup complete)
$pdo = null;
if (\App\Config::env('DB_DATABASE', '') !== '') {
    try {
        $pdo = \App\Database::connect(\App\Config::db());
        \App\Config::loadFromDb($pdo);
    } catch (\Throwable $e) {
        // DB not ready yet
    }
}
