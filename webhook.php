<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

use App\Config;
use App\TelegramBot;

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo 'Method Not Allowed'; exit;
}

// Validate webhook secret
$incomingSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
$expectedSecret = Config::env('TELEGRAM_WEBHOOK_SECRET', '');
if ($expectedSecret && !hash_equals($expectedSecret, $incomingSecret)) {
    http_response_code(403); echo 'Forbidden'; exit;
}

// Parse update
$rawBody = file_get_contents('php://input');
$update  = json_decode($rawBody, true);
if (!$update) { http_response_code(200); echo 'ok'; exit; }

// Dispatch to bot handler
require_once __DIR__ . '/bot/BotHandler.php';

$token  = Config::env('TELEGRAM_BOT_TOKEN', '');
if (!$token) { http_response_code(200); echo 'ok'; exit; }

$bot     = new TelegramBot($token);
$handler = new BotHandler($bot, $pdo);
$handler->handle($update);

http_response_code(200);
echo 'ok';
