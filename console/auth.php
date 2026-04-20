<?php
declare(strict_types=1);

// Must be required AFTER bootstrap.php
// Usage: require_once __DIR__ . '/auth.php';

use App\Config;

if (!isset($pdo) || !Config::isSetupComplete()) {
    header('Location: /setup.php'); exit;
}

// Admin password hash from DB config; fall back to env
$adminHash = Config::get('console_password_hash', '');

if ($adminHash === '') {
    // No password set yet — redirect to first-time console password setup
    header('Location: /console/passwd.php'); exit;
}

// Check session
if (empty($_SESSION['console_admin'])) {
    $redir = urlencode($_SERVER['REQUEST_URI'] ?? '/console/');
    header("Location: /console/login.php?next={$redir}"); exit;
}

// Rotate session ID periodically (every 30 min) to prevent fixation
if (empty($_SESSION['console_last_rotate']) || (time() - $_SESSION['console_last_rotate']) > 1800) {
    session_regenerate_id(true);
    $_SESSION['console_last_rotate'] = time();
}

// CSRF helper functions
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool {
    $tok = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return hash_equals(csrf_token(), $tok);
}

function csrf_enforce(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        die('<h1>403 Invalid CSRF Token</h1>');
    }
}
