<?php
declare(strict_types=1);

use App\TelegramBot;
use App\Order;
use App\Logger;
use App\Config;

class OrdersCommand
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

    // ------------------------------------------------------------------
    // Show order list menu
    // ------------------------------------------------------------------
    public function showList(int $chatId, ?int $msgId = null): void
    {
        try {
            $pending = (int) $this->pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
            $total   = (int) $this->pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        } catch (\Exception $e) { $pending = 0; $total = 0; }

        $text     = "📦 <b>Manajemen Order</b>\n\nTotal: <b>{$total}</b> order | Pending: <b>{$pending}</b>";
        $keyboard = TelegramBot::inlineKeyboard([
            [TelegramBot::btn("🔔 Pending ({$pending})", 'orders_pending'), TelegramBot::btn('📋 Semua', 'orders_all')],
            [TelegramBot::btn('← Menu', 'menu_main')],
        ]);

        if ($msgId) {
            $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
        } else {
            $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    // ------------------------------------------------------------------
    // Show pending orders
    // ------------------------------------------------------------------
    public function showPending(int $chatId, ?int $msgId = null): void
    {
        $orderModel = new Order($this->pdo);
        $orders     = $orderModel->getPending(10);

        if (empty($orders)) {
            $text = "📦 <b>Pending Orders</b>\n\n✅ Tidak ada order yang menunggu konfirmasi.";
            $keyboard = TelegramBot::inlineKeyboard([[TelegramBot::btn('← Kembali', 'menu_orders')]]);
            if ($msgId) {
                $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
            } else {
                $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
            }
            return;
        }

        $text = "🔔 <b>Order Pending</b> — " . count($orders) . " item\n\n";
        foreach ($orders as $o) {
            $elapsed  = $this->timeAgo($o['created_at']);
            $text .= "• <code>{$o['order_code']}</code> — {$o['email']}\n";
            $text .= "  💰 " . Order::formatRp((int)$o['amount']) . " | {$o['method']} | {$elapsed}\n\n";
        }

        // Build action buttons for each order (show first 5)
        $rows = [];
        foreach (array_slice($orders, 0, 5) as $o) {
            $rows[] = [
                TelegramBot::btn('✅ ' . $o['order_code'], 'confirm_' . $o['order_code']),
                TelegramBot::btn('❌ Tolak', 'reject_'  . $o['order_code']),
                TelegramBot::btn('👁', 'detail_' . $o['order_code']),
            ];
        }
        $rows[] = [TelegramBot::btn('← Kembali', 'menu_orders')];
        $keyboard = TelegramBot::inlineKeyboard($rows);

        if ($msgId) {
            $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
        } else {
            $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    // ------------------------------------------------------------------
    // Show all orders
    // ------------------------------------------------------------------
    public function showAll(int $chatId, ?int $msgId = null): void
    {
        $orderModel = new Order($this->pdo);
        $orders     = $orderModel->getAll(15);

        if (empty($orders)) {
            $text     = "📋 <b>Semua Order</b>\n\nBelum ada order masuk.";
            $keyboard = TelegramBot::inlineKeyboard([[TelegramBot::btn('← Kembali', 'menu_orders')]]);
            if ($msgId) {
                $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
            } else {
                $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
            }
            return;
        }

        $statusIcon = ['pending'=>'⏳','paid'=>'💸','confirmed'=>'✅','rejected'=>'❌','expired'=>'⌛'];
        $text = "📋 <b>Semua Order</b> (terbaru 15)\n\n";
        foreach ($orders as $o) {
            $icon = $statusIcon[$o['status']] ?? '•';
            $text .= "{$icon} <code>{$o['order_code']}</code> — {$o['email']}\n";
            $text .= "   " . Order::formatRp((int)$o['amount']) . " | {$o['method']} | {$o['status']}\n";
            $text .= "   🕐 " . date('d/m/y H:i', strtotime($o['created_at'])) . "\n\n";
        }

        $keyboard = TelegramBot::inlineKeyboard([[TelegramBot::btn('← Kembali', 'menu_orders')]]);

        if ($msgId) {
            $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
        } else {
            $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    // ------------------------------------------------------------------
    // Order detail
    // ------------------------------------------------------------------
    public function detail(int $chatId, ?int $msgId, string $code): void
    {
        $orderModel = new Order($this->pdo);
        $o          = $orderModel->findByCode($code);

        if (!$o) {
            $this->bot->sendMessage($chatId, "❌ Order <code>{$code}</code> tidak ditemukan.");
            return;
        }

        $statusIcon = ['pending'=>'⏳','paid'=>'💸','confirmed'=>'✅','rejected'=>'❌','expired'=>'⌛'];
        $icon       = $statusIcon[$o['status']] ?? '•';
        $siteUrl    = Config::get('site_url', '');

        $text = <<<HTML
🗂 <b>Detail Order</b>

📋 <b>Kode:</b> <code>{$o['order_code']}</code>
{$icon} <b>Status:</b> {$o['status']}
📧 <b>Email:</b> {$o['email']}
💳 <b>Metode:</b> {$o['method']}
💰 <b>Total:</b> {$this->formatRp((int)$o['amount'])}
🌐 <b>IP:</b> <code>{$o['ip_address']}</code>
🕐 <b>Dibuat:</b> {$o['created_at']}
⏰ <b>Expired:</b> {$o['expires_at']}
HTML;

        if ($o['status'] === 'rejected') {
            $text .= "\n📝 <b>Alasan Tolak:</b> " . htmlspecialchars($o['rejected_reason'] ?? '-');
        }
        if ($o['status'] === 'confirmed') {
            $text .= "\n✅ <b>Dikonfirmasi:</b> " . $o['confirmed_at'];
        }

        $rows = [];
        if ($o['status'] === 'pending') {
            $rows[] = [
                TelegramBot::btn('✅ Konfirmasi', 'confirm_' . $code),
                TelegramBot::btn('❌ Tolak', 'reject_'   . $code),
            ];
        }
        $rows[] = [TelegramBot::btn('← Kembali', 'orders_pending')];
        $keyboard = TelegramBot::inlineKeyboard($rows);

        if ($msgId) {
            $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
        } else {
            $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    // ------------------------------------------------------------------
    // Confirm order
    // ------------------------------------------------------------------
    public function confirm(int $chatId, ?int $msgId, string $code): void
    {
        $orderModel = new Order($this->pdo);
        $success    = $orderModel->confirm($code);

        if (!$success) {
            if ($msgId) {
                $this->bot->editMessageText($chatId, $msgId,
                    "⚠️ Order <code>{$code}</code> tidak bisa dikonfirmasi.\nMungkin sudah diproses sebelumnya.",
                    ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Menu', 'menu_orders')]])]
                );
            }
            return;
        }

        $ord    = $orderModel->findByCode($code);
        $logger = new Logger($this->pdo, $this->bot, $this->adminId);
        if ($ord) $logger->notifyPaymentConfirmed($ord);

        $text = "✅ <b>Order dikonfirmasi!</b>\n\n<code>{$code}</code>\nEmail: " . ($ord['email'] ?? '-');
        $keyboard = TelegramBot::inlineKeyboard([
            [TelegramBot::btn('📦 Lihat Order Lain', 'orders_pending')],
            [TelegramBot::btn('← Menu Utama', 'menu_main')],
        ]);

        if ($msgId) {
            $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
        } else {
            $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function timeAgo(string $dateStr): string
    {
        $diff = time() - strtotime($dateStr);
        if ($diff < 60)   return $diff . ' detik lalu';
        if ($diff < 3600) return floor($diff/60) . ' menit lalu';
        if ($diff < 86400)return floor($diff/3600) . ' jam lalu';
        return floor($diff/86400) . ' hari lalu';
    }

    private function formatRp(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
