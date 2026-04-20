<?php
declare(strict_types=1);

namespace App;

use PDO;

class Logger
{
    private PDO         $pdo;
    private ?TelegramBot $bot;
    private string|int  $adminChatId;

    public function __construct(PDO $pdo, ?TelegramBot $bot = null, string|int $adminChatId = 0)
    {
        $this->pdo         = $pdo;
        $this->bot         = $bot;
        $this->adminChatId = $adminChatId;
    }

    // ------------------------------------------------------------------
    // Traffic / Event logging to DB
    // ------------------------------------------------------------------

    public function log(string $page, string $action = '', array $data = []): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO traffic_logs 
                 (session_id, page, action, ip_address, user_agent, referer, data)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                session_id() ?: null,
                $page,
                $action ?: null,
                self::getIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $_SERVER['HTTP_REFERER'] ?? null,
                !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Exception $e) {
            // Silent fail - logging should never break the app
        }
    }

    // ------------------------------------------------------------------
    // Telegram notifications to admin
    // ------------------------------------------------------------------

    public function notifyNewOrder(array $order): void
    {
        if (!$this->bot || !$this->adminChatId) return;
        $icon = '🛒';
        $text = <<<HTML
{$icon} <b>ORDER BARU!</b>

📋 <b>Kode Order:</b> <code>{$order['order_code']}</code>
📧 <b>Email:</b> {$order['email']}
💳 <b>Metode Aktivasi:</b> {$order['method']}
💰 <b>Total:</b> Rp {$this->formatRp($order['amount'])}
🕐 <b>Expires:</b> {$order['expires_at']}
HTML;

        $keyboard = TelegramBot::inlineKeyboard([
            [
                TelegramBot::btn('✅ Konfirmasi', 'confirm_' . $order['order_code']),
                TelegramBot::btn('❌ Tolak', 'reject_' . $order['order_code']),
            ],
            [
                TelegramBot::btn('👁 Detail', 'detail_' . $order['order_code']),
            ],
        ]);

        $this->bot->sendMessage($this->adminChatId, $text, [
            'reply_markup' => $keyboard,
        ]);
    }

    public function notifyTraffic(string $event, array $data = []): void
    {
        if (!$this->bot || !$this->adminChatId) return;
        // Only log significant events to Telegram (not page views)
        $significantEvents = ['new_visit', 'checkout_start', 'payment_submit', 'activation_click'];
        if (!in_array($event, $significantEvents)) return;

        $icons = [
            'new_visit'       => '👀',
            'checkout_start'  => '🛍️',
            'payment_submit'  => '💸',
            'activation_click'=> '🔗',
        ];

        $icon   = $icons[$event] ?? '📌';
        $detail = '';
        foreach ($data as $k => $v) {
            $detail .= "\n  • <b>{$k}:</b> {$v}";
        }

        $text = "{$icon} <b>{$event}</b>\n<code>" . self::getIp() . "</code>{$detail}";
        $this->bot->sendMessage($this->adminChatId, $text);
    }

    public function notifyPaymentConfirmed(array $order): void
    {
        if (!$this->bot || !$this->adminChatId) return;
        $text = <<<HTML
✅ <b>PEMBAYARAN DIKONFIRMASI</b>

📋 <code>{$order['order_code']}</code>
📧 {$order['email']}
💰 Rp {$this->formatRp($order['amount'])}
HTML;
        $this->bot->sendMessage($this->adminChatId, $text);
    }

    public function notifyPaymentRejected(array $order, string $reason): void
    {
        if (!$this->bot || !$this->adminChatId) return;
        $text = <<<HTML
❌ <b>PEMBAYARAN DITOLAK</b>

📋 <code>{$order['order_code']}</code>
📧 {$order['email']}
📝 Alasan: {$reason}
HTML;
        $this->bot->sendMessage($this->adminChatId, $text);
    }

    // ------------------------------------------------------------------
    // Stats
    // ------------------------------------------------------------------

    public function getStats(): array
    {
        $stats = [];

        try {
            // Today's traffic
            $row = $this->pdo->query(
                "SELECT COUNT(*) as cnt FROM traffic_logs WHERE DATE(created_at) = CURDATE()"
            )->fetch();
            $stats['today_visits'] = (int) ($row['cnt'] ?? 0);

            // Total orders
            $row = $this->pdo->query(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END) as revenue
                 FROM orders"
            )->fetch();

            $stats['orders_total']     = (int) ($row['total']     ?? 0);
            $stats['orders_confirmed'] = (int) ($row['confirmed'] ?? 0);
            $stats['orders_pending']   = (int) ($row['pending']   ?? 0);
            $stats['orders_rejected']  = (int) ($row['rejected']  ?? 0);
            $stats['revenue_total']    = (int) ($row['revenue']   ?? 0);

            // Today revenue
            $row = $this->pdo->query(
                "SELECT SUM(amount) as rev FROM orders WHERE status='confirmed' AND DATE(confirmed_at)=CURDATE()"
            )->fetch();
            $stats['today_revenue'] = (int) ($row['rev'] ?? 0);

        } catch (\Exception $e) {
            // Return empty stats
        }

        return $stats;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function formatRp(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    public static function getIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }
}
