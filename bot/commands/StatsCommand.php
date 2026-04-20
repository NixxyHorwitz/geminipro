<?php
declare(strict_types=1);

use App\TelegramBot;
use App\Logger;
use App\Config;

class StatsCommand
{
    private TelegramBot $bot;
    private \PDO        $pdo;

    public function __construct(TelegramBot $bot, \PDO $pdo)
    {
        $this->bot = $bot;
        $this->pdo = $pdo;
    }

    public function show(int $chatId, ?int $msgId = null): void
    {
        $adminId = (int) Config::get('telegram_admin_chat_id', 0);
        $logger  = new Logger($this->pdo, $this->bot, $adminId);
        $stats   = $logger->getStats();

        // Weekly stats
        try {
            $week = $this->pdo->query(
                "SELECT COUNT(*) as cnt, SUM(amount) as rev
                 FROM orders WHERE status='confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            )->fetch();
            $weekOrders  = (int) ($week['cnt'] ?? 0);
            $weekRevenue = (int) ($week['rev'] ?? 0);
        } catch (\Exception $e) { $weekOrders = 0; $weekRevenue = 0; }

        // Top traffic pages
        try {
            $traffic = $this->pdo->query(
                "SELECT page, COUNT(*) as cnt FROM traffic_logs 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY page ORDER BY cnt DESC LIMIT 5"
            )->fetchAll();
        } catch (\Exception $e) { $traffic = []; }

        $rev     = 'Rp ' . number_format($stats['revenue_total'] ?? 0, 0, ',', '.');
        $todRev  = 'Rp ' . number_format($stats['today_revenue'] ?? 0, 0, ',', '.');
        $wkRev   = 'Rp ' . number_format($weekRevenue, 0, ',', '.');

        $text = <<<HTML
📊 <b>Statistik Toko</b>
<i>Update: {$this->now()}</i>

<b>💰 Revenue</b>
├ Hari ini: <b>{$todRev}</b>
├ 7 hari: <b>{$wkRev}</b>
└ Total: <b>{$rev}</b>

<b>📦 Orders</b>
├ Total: <b>{$stats['orders_total']}</b>
├ ✅ Confirmed: <b>{$stats['orders_confirmed']}</b>
├ ⏳ Pending: <b>{$stats['orders_pending']}</b>
└ ❌ Rejected: <b>{$stats['orders_rejected']}</b>

<b>👀 Traffic (Hari ini)</b>
└ Pengunjung: <b>{$stats['today_visits']}</b>

HTML;

        if (!empty($traffic)) {
            $text .= "<b>📈 Top Pages (24h)</b>\n";
            foreach ($traffic as $t) {
                $text .= "├ <code>{$t['page']}</code>: {$t['cnt']}x\n";
            }
        }

        $keyboard = TelegramBot::inlineKeyboard([
            [TelegramBot::btn('🔄 Refresh', 'menu_stats')],
            [TelegramBot::btn('← Menu', 'menu_main')],
        ]);

        if ($msgId) {
            $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
        } else {
            $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    private function now(): string
    {
        return date('d/m/Y H:i:s');
    }
}
