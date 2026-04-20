<?php
declare(strict_types=1);

use App\TelegramBot;
use App\QrisHelper;
use App\Config;

class QrisCommand
{
    private TelegramBot $bot;
    private \PDO        $pdo;

    public function __construct(TelegramBot $bot, \PDO $pdo)
    {
        $this->bot = $bot;
        $this->pdo = $pdo;
    }

    // ------------------------------------------------------------------
    // Prompt admin to send QRIS photo
    // ------------------------------------------------------------------
    public function prompt(int $chatId, ?int $msgId = null): void
    {
        // Show current QRIS info
        $current = $this->getCurrent();
        $info    = '';
        if ($current) {
            $name = htmlspecialchars($current['merchant_name'] ?? 'Unknown');
            $info = "\n\n✅ <b>QRIS Aktif:</b> {$name}\n<i>Upload baru untuk mengganti.</i>";
        } else {
            $info = "\n\n⚠️ Belum ada QRIS yang dikonfigurasi.";
        }

        $text = "💳 <b>Upload QRIS</b>{$info}\n\n📸 Kirimkan <b>foto</b> QRIS Anda (screenshot atau foto fisik).\nSistem akan membaca data QR otomatis dan menyimpan template untuk pembayaran dinamis.";

        $keyboard = TelegramBot::inlineKeyboard([[TelegramBot::btn('← Menu', 'menu_main')]]);

        // Set session state to await photo
        // We need access to handler session — use a workaround via DB
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO bot_sessions (chat_id, state, data) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE state=VALUES(state), data=VALUES(data), updated_at=NOW()"
            );
            $stmt->execute([$chatId, 'await_qris_photo', json_encode([])]);
        } catch (\Exception $e) {}

        if ($msgId) {
            $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
        } else {
            $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    // ------------------------------------------------------------------
    // Handle inbound photo
    // ------------------------------------------------------------------
    public function handlePhoto(int $chatId, array $msg, object $handler): void
    {
        $photos = $msg['photo'] ?? null;
        if (!$photos) {
            $this->bot->sendMessage($chatId,
                "❌ Bukan foto. Kirimkan gambar QRIS Anda.\nAtau ketik /start untuk kembali ke menu.");
            return;
        }

        // Get largest photo
        $photo   = end($photos);
        $fileId  = $photo['file_id'];
        $token   = Config::env('TELEGRAM_BOT_TOKEN', Config::get('telegram_bot_token', ''));

        // Download photo
        $fileInfo = $this->bot->call('getFile', ['file_id' => $fileId]);
        if (empty($fileInfo['ok'])) {
            $this->bot->sendMessage($chatId, "❌ Gagal mengambil file. Coba lagi.");
            return;
        }

        $filePath = $fileInfo['result']['file_path'];
        $fileUrl  = "https://api.telegram.org/file/bot{$token}/{$filePath}";

        // Save to uploads dir
        $uploadDir = dirname(__DIR__, 2) . '/uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $localPath = $uploadDir . '/qris_' . time() . '.jpg';

        $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $img = @file_get_contents($fileUrl, false, $ctx);
        if (!$img) {
            $this->bot->sendMessage($chatId, "❌ Gagal mengunduh foto. Coba lagi.");
            return;
        }
        file_put_contents($localPath, $img);

        $this->bot->sendMessage($chatId, "🔍 Mendekode QR Code dari gambar...");

        // Try to decode QR
        $qrisString = QrisHelper::decodeFromImage($localPath);

        if (!$qrisString) {
            // Ask user to input manually
            $handler->setSession($chatId, 'await_qris_manual', ['image' => $localPath]);
            $this->bot->sendMessage($chatId,
                "⚠️ <b>QR tidak bisa dibaca otomatis</b>\n\nSilakan <b>copy-paste teks raw QRIS</b> Anda secara manual.\n\n<i>Cara mendapat raw QRIS: gunakan app QR scanner seperti QR & Barcode Scanner, lalu salin teksnya.</i>",
                ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Batal', 'menu_main')]])]
            );

            // Override session
            try {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO bot_sessions (chat_id, state, data) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE state=VALUES(state), data=VALUES(data), updated_at=NOW()"
                );
                $stmt->execute([$chatId, 'await_qris_manual', json_encode(['image' => $localPath])]);
            } catch (\Exception $e) {}
            return;
        }

        $this->saveQris($chatId, $qrisString, $localPath, $handler);
    }

    // ------------------------------------------------------------------
    // Save QRIS to DB
    // ------------------------------------------------------------------
    public function saveQris(int $chatId, string $rawString, string $imagePath, object $handler): void
    {
        // Validate
        if (!QrisHelper::validate($rawString)) {
            $this->bot->sendMessage($chatId,
                "⚠️ <b>QRIS tidak valid</b> (CRC mismatch).\n\nPastikan teks utuh dan tidak ada karakter hilang.\nCoba kirim ulang foto atau paste manual.");
            return;
        }

        $merchantName = QrisHelper::getMerchantName($rawString);

        // Deactivate old ones
        try {
            $this->pdo->exec("UPDATE qris_templates SET active=0");
        } catch (\Exception $e) {}

        // Insert new
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO qris_templates (raw_string, merchant_name, image_path, active)
                 VALUES (?, ?, ?, 1)"
            );
            $stmt->execute([$rawString, $merchantName, $imagePath]);
        } catch (\Exception $e) {
            $this->bot->sendMessage($chatId, "❌ Gagal menyimpan QRIS ke database: " . $e->getMessage());
            return;
        }

        $handler->clearSession($chatId);

        // Test: generate dynamic QRIS with 309000
        $price   = (int) Config::get('product_price', 309000);
        $dynamic = QrisHelper::setAmount($rawString, $price);
        $preview = QrisHelper::generateQrImage($dynamic, 250);

        $text = <<<HTML
✅ <b>QRIS Berhasil Disimpan!</b>

🏪 <b>Merchant:</b> {$merchantName}
💰 <b>Preview nominal:</b> Rp {$this->formatRp($price)}

QR dinamis sekarang akan otomatis menyesuaikan nominal saat checkout.
HTML;

        $keyboard = TelegramBot::inlineKeyboard([[TelegramBot::btn('← Menu', 'menu_main')]]);
        $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);

        // Send preview QR image
        if ($preview) {
            $previewPath = dirname($imagePath) . '/qris_preview_' . time() . '.png';
            $imgData     = base64_decode(substr($preview, 22)); // strip data:image/png;base64,
            file_put_contents($previewPath, $imgData);
            $this->bot->sendPhoto($chatId, $previewPath, "Preview QRIS dinamis — Rp " . $this->formatRp($price));
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function getCurrent(): ?array
    {
        try {
            $r = $this->pdo->query("SELECT * FROM qris_templates WHERE active=1 ORDER BY id DESC LIMIT 1")->fetch();
            return $r ?: null;
        } catch (\Exception $e) { return null; }
    }

    private function formatRp(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
