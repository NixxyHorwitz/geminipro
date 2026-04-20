<?php
declare(strict_types=1);

require_once __DIR__ . '/commands/StartCommand.php';
require_once __DIR__ . '/commands/QrisCommand.php';
require_once __DIR__ . '/commands/OrdersCommand.php';
require_once __DIR__ . '/commands/StatsCommand.php';
require_once __DIR__ . '/commands/SettingsCommand.php';
require_once __DIR__ . '/commands/BroadcastCommand.php';

use App\TelegramBot;
use App\Config;
use App\Order;
use App\Logger;

class BotHandler
{
    private TelegramBot $bot;
    private \PDO        $pdo;
    private int         $adminId;

    public function __construct(TelegramBot $bot, \PDO $pdo)
    {
        $this->bot     = $bot;
        $this->pdo     = $pdo;
        $this->adminId = (int) Config::get('telegram_admin_chat_id', 0);
    }

    public function handle(array $update): void
    {
        // Load DB config
        Config::loadFromDb($this->pdo);

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }
    }

    // ------------------------------------------------------------------
    // Message handler
    // ------------------------------------------------------------------
    private function handleMessage(array $msg): void
    {
        $chatId = (int) ($msg['chat']['id'] ?? 0);
        $text   = trim($msg['text'] ?? '');

        // Security: only admin chat
        if ($chatId !== $this->adminId) {
            $this->bot->sendMessage($chatId,
                "🚫 Akses ditolak. Bot ini hanya untuk admin.\n\nKunjungi: <a href='" . Config::get('site_url') . "'>" . Config::get('site_url') . "</a>");
            return;
        }

        // Check for pending state (multi-step commands)
        $session = $this->getSession($chatId);
        $state   = $session['state'] ?? '';

        // Handle state-based input first
        if ($state !== '') {
            $this->handleState($chatId, $text, $state, $session, $msg);
            return;
        }

        // Commands
        if (str_starts_with($text, '/')) {
            $cmd = strtolower(explode(' ', explode('@', $text)[0])[0]);
            $this->routeCommand($cmd, $chatId, $text, $msg);
            return;
        }

        // Default: show menu
        $this->sendMainMenu($chatId);
    }

    // ------------------------------------------------------------------
    // Callback (inline button) handler
    // ------------------------------------------------------------------
    private function handleCallback(array $cb): void
    {
        $chatId   = (int) ($cb['message']['chat']['id'] ?? 0);
        $msgId    = (int) ($cb['message']['message_id'] ?? 0);
        $data     = $cb['data'] ?? '';
        $cbId     = $cb['id'] ?? '';

        if ($chatId !== $this->adminId) {
            $this->bot->answerCallbackQuery($cbId, '🚫 Akses ditolak', true);
            return;
        }

        $this->bot->answerCallbackQuery($cbId);

        // Route callbacks
        if ($data === 'menu_main')          { $this->editMainMenu($chatId, $msgId); return; }
        if ($data === 'menu_orders')        { (new OrdersCommand($this->bot, $this->pdo))->showList($chatId, $msgId); return; }
        if ($data === 'menu_stats')         { (new StatsCommand($this->bot, $this->pdo))->show($chatId, $msgId); return; }
        if ($data === 'menu_qris')          { (new QrisCommand($this->bot, $this->pdo))->prompt($chatId, $msgId); return; }
        if ($data === 'menu_settings')      { (new SettingsCommand($this->bot, $this->pdo))->show($chatId, $msgId); return; }
        if ($data === 'menu_broadcast')     { (new BroadcastCommand($this->bot, $this->pdo))->prompt($chatId, $msgId); return; }
        if ($data === 'orders_pending')     { (new OrdersCommand($this->bot, $this->pdo))->showPending($chatId, $msgId); return; }
        if ($data === 'orders_all')         { (new OrdersCommand($this->bot, $this->pdo))->showAll($chatId, $msgId); return; }

        // Order actions: confirm_ORDERCODE / reject_ORDERCODE / detail_ORDERCODE
        if (str_starts_with($data, 'confirm_')) {
            $code = substr($data, 8);
            (new OrdersCommand($this->bot, $this->pdo))->confirm($chatId, $msgId, $code);
            return;
        }
        if (str_starts_with($data, 'reject_')) {
            $code = substr($data, 7);
            $this->setSession($chatId, 'reject_reason', ['code' => $code, 'msg_id' => $msgId]);
            $this->bot->editMessageText($chatId, $msgId,
                "✏️ Ketik alasan penolakan untuk order <code>{$code}</code>:",
                ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Batal', 'menu_orders')]])]
            );
            return;
        }
        if (str_starts_with($data, 'detail_')) {
            $code = substr($data, 7);
            (new OrdersCommand($this->bot, $this->pdo))->detail($chatId, $msgId, $code);
            return;
        }

        // Settings callbacks
        if (str_starts_with($data, 'set_')) {
            (new SettingsCommand($this->bot, $this->pdo))->handleCallback($chatId, $msgId, $data, $this);
            return;
        }
    }

    // ------------------------------------------------------------------
    // Command router
    // ------------------------------------------------------------------
    private function routeCommand(string $cmd, int $chatId, string $text, array $msg): void
    {
        switch ($cmd) {
            case '/start':
                $this->sendMainMenu($chatId);
                break;
            case '/orders':
                (new OrdersCommand($this->bot, $this->pdo))->showList($chatId);
                break;
            case '/stats':
                (new StatsCommand($this->bot, $this->pdo))->show($chatId);
                break;
            case '/qris':
                (new QrisCommand($this->bot, $this->pdo))->prompt($chatId);
                break;
            case '/settings':
                (new SettingsCommand($this->bot, $this->pdo))->show($chatId);
                break;
            case '/broadcast':
                (new BroadcastCommand($this->bot, $this->pdo))->prompt($chatId);
                break;
            case '/help':
                $this->sendHelp($chatId);
                break;
            default:
                $this->sendMainMenu($chatId);
        }
    }

    // ------------------------------------------------------------------
    // State machine (multi-step interactions)
    // ------------------------------------------------------------------
    public function handleState(int $chatId, string $text, string $state, array $session, array $msg): void
    {
        $data = $session['data'] ?? [];

        switch ($state) {
            case 'reject_reason':
                $code = $data['code'] ?? '';
                if ($code) {
                    $orderModel = new Order($this->pdo);
                    $success    = $orderModel->reject($code, $text);
                    $this->clearSession($chatId);

                    if ($success) {
                        $logger = new Logger($this->pdo, $this->bot, $this->adminId);
                        $ord    = $orderModel->findByCode($code);
                        if ($ord) $logger->notifyPaymentRejected($ord, $text);
                        $this->bot->sendMessage($chatId,
                            "✅ Order <code>{$code}</code> berhasil ditolak.\nAlasan: {$text}",
                            ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Menu', 'menu_main')]])]
                        );
                    } else {
                        $this->bot->sendMessage($chatId, "❌ Gagal menolak order. Mungkin sudah diproses.");
                    }
                }
                break;

            case 'set_price':
                $price = (int) preg_replace('/\D/', '', $text);
                if ($price >= 1000) {
                    Config::set($this->pdo, 'product_price', (string) $price);
                    $this->clearSession($chatId);
                    $formatted = 'Rp ' . number_format($price, 0, ',', '.');
                    $this->bot->sendMessage($chatId, "✅ Harga berhasil diubah ke <b>{$formatted}</b>",
                        ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Menu', 'menu_main')]])]
                    );
                } else {
                    $this->bot->sendMessage($chatId, "❌ Harga tidak valid. Minimal Rp 1.000. Coba lagi:");
                }
                break;

            case 'await_qris_photo':
                // Handle QRIS photo upload
                (new QrisCommand($this->bot, $this->pdo))->handlePhoto($chatId, $msg, $this);
                break;

            case 'broadcast_msg':
                (new BroadcastCommand($this->bot, $this->pdo))->send($chatId, $text);
                $this->clearSession($chatId);
                break;

            case 'set_site_url':
                $url = rtrim(trim($text), '/');
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    Config::set($this->pdo, 'site_url', $url);
                    Config::writeEnv(['APP_URL' => $url]);
                    $this->clearSession($chatId);
                    $this->bot->sendMessage($chatId,
                        "✅ Site URL berhasil diubah ke:\n<code>{$url}</code>",
                        ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Settings', 'menu_settings')]])]
                    );
                } else {
                    $this->bot->sendMessage($chatId, "❌ URL tidak valid. Contoh: <code>https://yourdomain.com/googlepro</code>");
                }
                break;

            case 'await_qris_manual':
                // User pasted raw QRIS string manually
                $raw = trim($text);
                if (strlen($raw) > 20) {
                    $imagePath = $data['image'] ?? '';
                    (new QrisCommand($this->bot, $this->pdo))->saveQris($chatId, $raw, $imagePath, $this);
                } else {
                    $this->bot->sendMessage($chatId, "❌ String QRIS terlalu pendek. Pastikan Anda paste teks lengkap.");
                }
                break;
        }
    }

    // ------------------------------------------------------------------
    // Main Menu
    // ------------------------------------------------------------------
    public function sendMainMenu(int $chatId): void
    {
        $siteUrl = Config::get('site_url', '-');
        $text = <<<HTML
🤖 <b>Google AI Pro Admin Panel</b>

Selamat datang! Gunakan menu di bawah untuk mengelola toko.

🌐 <b>Site:</b> <a href="{$siteUrl}">{$siteUrl}</a>
HTML;
        $keyboard = $this->mainMenuKeyboard();
        $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
    }

    private function editMainMenu(int $chatId, int $msgId): void
    {
        $siteUrl = Config::get('site_url', '-');
        $text    = "🤖 <b>Google AI Pro Admin Panel</b>\n\n🌐 <b>Site:</b> <a href=\"{$siteUrl}\">{$siteUrl}</a>";
        $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $this->mainMenuKeyboard()]);
    }

    private function mainMenuKeyboard(): array
    {
        // Get pending order count
        $pending = 0;
        try {
            $r = $this->pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
            $pending = (int) $r;
        } catch (\Exception $e) {}

        $pendingLabel = $pending > 0 ? "📦 Order ({$pending} pending)" : '📦 Order';

        return TelegramBot::inlineKeyboard([
            [TelegramBot::btn($pendingLabel, 'menu_orders'), TelegramBot::btn('📊 Statistik', 'menu_stats')],
            [TelegramBot::btn('💳 Set QRIS', 'menu_qris'),   TelegramBot::btn('⚙️ Settings', 'menu_settings')],
            [TelegramBot::btn('📢 Broadcast', 'menu_broadcast')],
        ]);
    }

    private function sendHelp(int $chatId): void
    {
        $text = <<<HTML
📖 <b>Daftar Perintah</b>

/start — Menu utama
/orders — Daftar order
/stats — Statistik penjualan
/qris — Upload QRIS baru
/settings — Pengaturan
/broadcast — Kirim broadcast
/help — Panduan ini
HTML;
        $this->bot->sendMessage($chatId, $text);
    }

    // ------------------------------------------------------------------
    // Session helpers
    // ------------------------------------------------------------------
    public function setSession(int $chatId, string $state, array $data = []): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO bot_sessions (chat_id, state, data) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE state=VALUES(state), data=VALUES(data), updated_at=NOW()"
            );
            $stmt->execute([$chatId, $state, json_encode($data)]);
        } catch (\Exception $e) {}
    }

    public function clearSession(int $chatId): void
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM bot_sessions WHERE chat_id=?");
            $stmt->execute([$chatId]);
        } catch (\Exception $e) {}
    }

    public function getSession(int $chatId): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT state, data FROM bot_sessions WHERE chat_id=?");
            $stmt->execute([$chatId]);
            $row = $stmt->fetch();
            if (!$row) return [];
            return [
                'state' => $row['state'] ?? '',
                'data'  => json_decode($row['data'] ?? '{}', true) ?? [],
            ];
        } catch (\Exception $e) { return []; }
    }
}
