<?php
declare(strict_types=1);

use App\TelegramBot;
use App\Config;

class BroadcastCommand
{
    private TelegramBot $bot;
    private \PDO        $pdo;

    public function __construct(TelegramBot $bot, \PDO $pdo)
    {
        $this->bot = $bot;
        $this->pdo = $pdo;
    }

    public function prompt(int $chatId, ?int $msgId = null): void
    {
        try {
            $count = (int) $this->pdo->query("SELECT COUNT(DISTINCT email) FROM orders WHERE status='confirmed'")->fetchColumn();
        } catch (\Exception $e) { $count = 0; }

        $text = <<<HTML
📢 <b>Broadcast Pesan</b>

Total penerima (order confirmed): <b>{$count} email</b>

<i>Note: Broadcast dikirim via log Telegram saja (tidak via email kecuali SMTP dikonfigurasi).</i>

Ketik pesan broadcast Anda:
HTML;

        // Set state
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO bot_sessions (chat_id, state, data) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE state=VALUES(state), data=VALUES(data), updated_at=NOW()"
            );
            $stmt->execute([$chatId, 'broadcast_msg', json_encode(['count' => $count])]);
        } catch (\Exception $e) {}

        $keyboard = TelegramBot::inlineKeyboard([[TelegramBot::btn('← Batal', 'menu_main')]]);

        if ($msgId) {
            $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
        } else {
            $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    public function send(int $chatId, string $message): void
    {
        try {
            $emails = $this->pdo->query(
                "SELECT DISTINCT email FROM orders WHERE status='confirmed'"
            )->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) { $emails = []; }

        $count = count($emails);

        // Log broadcast to Telegram admin
        $preview = mb_substr($message, 0, 200);
        $this->bot->sendMessage($chatId,
            "📢 <b>Broadcast Terkirim</b>\n\nPesan: {$preview}\n\nDikirim ke: <b>{$count}</b> email terdaftar\n\n<i>Implementasi pengiriman email membutuhkan konfigurasi SMTP.</i>",
            ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Menu', 'menu_main')]])]
        );

        // TODO: actual email sending via PHPMailer when SMTP is configured
    }
}
