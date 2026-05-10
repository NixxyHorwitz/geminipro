<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

$pageTitle  = 'Telegram Bot';
$activePage = 'bot';

$msg = '';
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$chatId = $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? '';
$webhookSecret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
$appUrl = $_ENV['APP_URL'] ?? 'https://'.$_SERVER['HTTP_HOST'];

if (empty($webhookSecret)) {
    $webhookSecret = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'sync') {
        $url = "https://api.telegram.org/bot$botToken/setWebhook";
        $webhookUrl = rtrim($appUrl, '/') . '/webhook.php?secret=' . $webhookSecret;
        $response = @file_get_contents($url . "?url=" . urlencode($webhookUrl));
        if ($response) {
            $res = json_decode($response, true);
            if ($res && $res['ok']) {
                $msg = "<div style='color:#10b981;background:rgba(16,185,129,0.1);padding:10px;border-radius:8px;margin-bottom:16px'>✅ Webhook tersinkronisasi!</div>";
            } else {
                $msg = "<div style='color:#ef4444;background:rgba(239,68,68,0.1);padding:10px;border-radius:8px;margin-bottom:16px'>❌ Gagal: " . htmlspecialchars($res['description'] ?? '') . "</div>";
            }
        } else {
            $msg = "<div style='color:#ef4444;background:rgba(239,68,68,0.1);padding:10px;border-radius:8px;margin-bottom:16px'>❌ Gagal menghubungi API Telegram.</div>";
        }
    } else {
        $botToken = trim($_POST['bot_token'] ?? '');
        $chatId = trim($_POST['chat_id'] ?? '');
        
        $envFile = dirname(__DIR__) . '/.env';
        $env = file_get_contents($envFile);
        
        $env = preg_replace('/^TELEGRAM_BOT_TOKEN=.*$/m', 'TELEGRAM_BOT_TOKEN=' . $botToken, $env);
        if (strpos($env, 'TELEGRAM_BOT_TOKEN=') === false) $env .= "\nTELEGRAM_BOT_TOKEN=" . $botToken;
        
        $env = preg_replace('/^TELEGRAM_ADMIN_CHAT_ID=.*$/m', 'TELEGRAM_ADMIN_CHAT_ID=' . $chatId, $env);
        if (strpos($env, 'TELEGRAM_ADMIN_CHAT_ID=') === false) $env .= "\nTELEGRAM_ADMIN_CHAT_ID=" . $chatId;
        
        $env = preg_replace('/^TELEGRAM_WEBHOOK_SECRET=.*$/m', 'TELEGRAM_WEBHOOK_SECRET=' . $webhookSecret, $env);
        if (strpos($env, 'TELEGRAM_WEBHOOK_SECRET=') === false) $env .= "\nTELEGRAM_WEBHOOK_SECRET=" . $webhookSecret;
        
        file_put_contents($envFile, $env);
        $msg = "<div style='color:#10b981;background:rgba(16,185,129,0.1);padding:10px;border-radius:8px;margin-bottom:16px'>✅ Pengaturan Bot disimpan!</div>";
    }
}

require __DIR__ . '/partials/header.php';
?>

<div class="card" style="max-width: 600px;">
  <h2 style="margin-top:0;font-size:18px">Konfigurasi Bot Telegram</h2>
  <p style="color:var(--c-text-sec);font-size:13px;margin-bottom:20px">
    Bot ini berfungsi ganda sebagai notifikasi pesanan baru (Order Pending) sekaligus sebagai sistem konfirmasi manual. Admin cukup merespons pesan bot dengan mengirim ID order (misal: GAP123) untuk mengubah status menjadi Success.
  </p>
  
  <?= $msg ?>

  <form method="POST">
    <div class="form-group">
      <label>Bot Token</label>
      <input type="text" name="bot_token" class="form-control" value="<?= htmlspecialchars($botToken) ?>" placeholder="123456789:AAHxxxxxxxxxxxxx" required>
      <div style="font-size:11px;color:var(--c-text-hint);margin-top:4px">Dapatkan dari @BotFather di Telegram</div>
    </div>
    <div class="form-group">
      <label>Admin Chat ID</label>
      <input type="text" name="chat_id" class="form-control" value="<?= htmlspecialchars($chatId) ?>" placeholder="Contoh: 7884836068" required>
      <div style="font-size:11px;color:var(--c-text-hint);margin-top:4px">ID chat kamu untuk menerima notifikasi.</div>
    </div>
    <div style="margin-top:20px;display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
    </div>
  </form>
</div>

<?php if ($botToken): ?>
<div class="card" style="max-width: 600px; margin-top:20px">
  <h3 style="margin-top:0;font-size:16px;display:flex;align-items:center;gap:8px">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
    Sinkronisasi Webhook
  </h3>
  <p style="color:var(--c-text-sec);font-size:13px">
    Tekan tombol di bawah ini agar Telegram mengetahui URL Webhook kamu. URL yang akan disinkronkan adalah:<br>
    <code style="font-size:11px;background:var(--c-bg);padding:4px;border-radius:4px;margin-top:6px;display:block;word-break:break-all">
      <?= htmlspecialchars(rtrim($appUrl, '/') . '/webhook.php?secret=' . $webhookSecret) ?>
    </code>
  </p>
  <form method="POST">
    <input type="hidden" name="action" value="sync">
    <button type="submit" class="btn" style="background:#10b981;color:#fff;border:none">Sync Webhook Sekarang</button>
  </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
