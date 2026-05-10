<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$secret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
$token  = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';

// Check secret if provided in URL (optional simple auth)
if (!empty($secret) && ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403);
    exit('Forbidden');
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update || !isset($update['message'])) {
    exit('OK'); // Ignore non-message updates silently
}

$message = $update['message'];
$chatId  = $message['chat']['id'] ?? '';
$text    = trim($message['text'] ?? '');

if (!$chatId || !$text) {
    exit('OK');
}

// Function to send reply
function sendReply($chatId, $text, $token) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'ignore_errors' => true
        ]
    ];
    file_get_contents($url, false, stream_context_create($options));
}

// Handle commands
if ($text === '/start') {
    sendReply($chatId, "Halo! Saya adalah Bot Successor GooglePro.\nKirimkan *Order Code* (contoh: GAP12345678) untuk mengonfirmasi pembayaran dan mengubah status menjadi Success.", $token);
    exit('OK');
}

// Handle order code confirmation (e.g. GAPXXXXX)
if (preg_match('/^GAP[A-Z0-9]+$/i', $text)) {
    $code = strtoupper($text);
    
    $stmt = $pdo->prepare("SELECT id, status, amount FROM orders WHERE order_code = ?");
    $stmt->execute([$code]);
    $order = $stmt->fetch();
    
    if (!$order) {
        sendReply($chatId, "❌ Order dengan kode <b>$code</b> tidak ditemukan.", $token);
    } elseif ($order['status'] === 'confirmed') {
        sendReply($chatId, "⚠️ Order <b>$code</b> sudah berstatus confirmed/success sebelumnya.", $token);
    } elseif ($order['status'] === 'expired' || $order['status'] === 'rejected') {
        sendReply($chatId, "❌ Order <b>$code</b> tidak bisa dikonfirmasi karena statusnya sudah {$order['status']}.", $token);
    } else {
        // Update status to confirmed
        $updateStmt = $pdo->prepare("UPDATE orders SET status='confirmed', confirmed_at=NOW() WHERE order_code=?");
        if ($updateStmt->execute([$code])) {
            sendReply($chatId, "✅ <b>BERHASIL!</b>\nOrder <b>$code</b> telah dikonfirmasi (Success).\nAnalytics dan Revenue telah terupdate.", $token);
        } else {
            sendReply($chatId, "❌ Gagal mengupdate order di database.", $token);
        }
    }
} else {
    sendReply($chatId, "Kirimkan Order Code yang valid (diawali GAP) untuk konfirmasi.", $token);
}

exit('OK');
