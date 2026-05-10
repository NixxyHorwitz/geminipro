<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$token  = \App\Config::env('TELEGRAM_BOT_TOKEN', '');

$input = file_get_contents('php://input');

// DEBUG LOG: Catat semua payload masuk
file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] IN: " . $input . "\n", FILE_APPEND);

$update = json_decode($input, true);

if (!$update || !isset($update['message'])) {
    file_put_contents(__DIR__ . '/webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] IGNORED: No message object\n", FILE_APPEND);
    exit('OK'); // Ignore non-message updates silently
}

$message = $update['message'];
$chatId  = $message['chat']['id'] ?? '';

// Function to send reply
function sendReply($chatId, $text, $token) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    // DEBUG LOG: Catat balasan bot
    $logMsg = "[" . date('Y-m-d H:i:s') . "] OUT to $chatId: " . str_replace("\n", " ", $text) . " | RESP: " . ($response ?: "cURL Error: $error") . "\n";
    file_put_contents(__DIR__ . '/webhook_debug.log', $logMsg, FILE_APPEND);
}

// Handle Photo uploads
if (isset($message['photo'])) {
    $photos = $message['photo'];
    $largestPhoto = end($photos);
    $fileId = $largestPhoto['file_id'];
    
    $url = "https://api.telegram.org/bot$token/getFile?file_id=" . urlencode($fileId);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response ?: '', true);
    if (isset($data['result']['file_path'])) {
        $filePath = $data['result']['file_path'];
        $downloadUrl = "https://api.telegram.org/file/bot$token/" . $filePath;
        
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        if (empty($ext)) $ext = 'jpg';
        $fileName = uniqid('telemage_') . '.' . $ext;
        
        $saveDir = __DIR__ . '/uploads/telemage/';
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }
        
        $savePath = $saveDir . $fileName;
        
        $ch = curl_init($downloadUrl);
        $fp = fopen($savePath, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        
        sendReply($chatId, "✅ Gambar berhasil diterima dan disimpan!\nFile: $fileName", $token);
    } else {
        sendReply($chatId, "❌ Gagal mendapatkan path gambar dari Telegram.", $token);
    }
    exit('OK');
}

$text    = trim($message['text'] ?? '');

if (!$chatId || !$text) {
    exit('OK');
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
