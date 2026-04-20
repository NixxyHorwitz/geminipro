<?php
declare(strict_types=1);

use App\TelegramBot;
use App\Config;

class SettingsCommand
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
        $price    = (int) Config::get('product_price', 309000);
        $siteUrl  = Config::get('site_url', '-');
        $gClientId = Config::get('google_client_id', '') ? '✅ Set' : '❌ Belum';

        $text = <<<HTML
⚙️ <b>Settings</b>

💰 <b>Harga Produk:</b> Rp {$this->formatRp($price)}
🌐 <b>Site URL:</b> <code>{$siteUrl}</code>
🔐 <b>Google SSO:</b> {$gClientId}
HTML;

        $keyboard = TelegramBot::inlineKeyboard([
            [TelegramBot::btn('✏️ Ubah Harga', 'set_price')],
            [TelegramBot::btn('🔐 Set Google SSO', 'set_google_sso')],
            [TelegramBot::btn('🌐 Set Site URL', 'set_site_url')],
            [TelegramBot::btn('🔄 Sync Webhook', 'set_sync_webhook')],
            [TelegramBot::btn('← Menu', 'menu_main')],
        ]);

        if ($msgId) {
            $this->bot->editMessageText($chatId, $msgId, $text, ['reply_markup' => $keyboard]);
        } else {
            $this->bot->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
        }
    }

    public function handleCallback(int $chatId, int $msgId, string $data, object $handler): void
    {
        switch ($data) {
            case 'set_price':
                $price = $this->formatRp((int) Config::get('product_price', 309000));
                $handler->setSession($chatId, 'set_price', []);
                $this->bot->editMessageText($chatId, $msgId,
                    "✏️ <b>Ubah Harga</b>\n\nHarga saat ini: <b>Rp {$price}</b>\n\nKetik harga baru dalam Rupiah (contoh: <code>309000</code>):",
                    ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Batal', 'menu_settings')]])]
                );
                break;

            case 'set_site_url':
                $handler->setSession($chatId, 'set_site_url', []);
                $this->bot->editMessageText($chatId, $msgId,
                    "🌐 <b>Ubah Site URL</b>\n\nURL saat ini: <code>" . Config::get('site_url', '-') . "</code>\n\nKetik URL baru (tanpa trailing slash):",
                    ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Batal', 'menu_settings')]])]
                );
                break;

            case 'set_google_sso':
                $this->bot->editMessageText($chatId, $msgId,
                    "🔐 <b>Google SSO Setup</b>\n\nUntuk mengaktifkan SSO, buka <a href=\"https://console.cloud.google.com\">Google Cloud Console</a>, buat OAuth 2.0 credentials, lalu masukkan via setup wizard di:\n<code>" . Config::get('site_url') . "/setup.php</code>",
                    ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Kembali', 'menu_settings')]])]
                );
                break;

            case 'set_sync_webhook':
                $token      = Config::get('telegram_bot_token', Config::env('TELEGRAM_BOT_TOKEN', ''));
                $secret     = Config::get('telegram_webhook_secret', Config::env('TELEGRAM_WEBHOOK_SECRET', ''));
                $siteUrl    = Config::get('site_url', '');
                $webhookUrl = $siteUrl . '/webhook.php';

                $bot    = new TelegramBot($token);
                $result = $bot->setWebhook($webhookUrl, $secret);
                $ok     = $result['ok'] ?? false;

                $this->bot->editMessageText($chatId, $msgId,
                    $ok
                        ? "✅ Webhook berhasil disync!\n<code>{$webhookUrl}</code>"
                        : "❌ Gagal sync webhook. Pastikan site URL publik dan dapat diakses HTTPS.",
                    ['reply_markup' => TelegramBot::inlineKeyboard([[TelegramBot::btn('← Settings', 'menu_settings')]])]
                );
                break;
        }
    }

    private function formatRp(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
