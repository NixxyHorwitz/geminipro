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
                $msg = "Webhook tersinkronisasi!";
                $msgType = 'success';
            } else {
                $msg = "Gagal: " . htmlspecialchars($res['description'] ?? '');
                $msgType = 'error';
            }
        } else {
            $msg = "Gagal menghubungi API Telegram.";
            $msgType = 'error';
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
        $msg = "Pengaturan Bot disimpan!";
        $msgType = 'success';
    }
}

require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Telegram Bot</h1>
  <p class="page-sub">Konfigurasi bot notifikasi dan konfirmasi manual pesanan</p>
</div>

<?php if ($msg): ?>
<div class="alert alert--<?= $msgType === 'error' ? 'error' : 'success' ?>" style="margin-bottom:20px">
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="two-col-grid">
  <!-- Bot Config -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Pengaturan Kredensial</div>
    </div>
    <div class="card__body">
      <p class="form-hint" style="margin-bottom:20px; font-size:13px; color:var(--c-text-sec)">
        Bot ini berfungsi ganda sebagai notifikasi pesanan baru (Pending) sekaligus sebagai sistem konfirmasi manual. Admin cukup membalas pesan bot dengan mengirim ID order (misal: GAP123) untuk mengubah status menjadi Success.
      </p>

      <form method="POST">
        <div class="form-group">
          <label class="form-label">Bot Token</label>
          <input type="text" name="bot_token" class="form-control" value="<?= htmlspecialchars($botToken) ?>" placeholder="123456789:AAHxxxxxxxxxxxxx" required>
          <div class="form-hint">Dapatkan dari @BotFather di aplikasi Telegram.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Admin Chat ID</label>
          <input type="text" name="chat_id" class="form-control" value="<?= htmlspecialchars($chatId) ?>" placeholder="Contoh: 7884836068" required>
          <div class="form-hint">ID chat pribadi Anda untuk menerima notifikasi.</div>
        </div>
        <button type="submit" class="btn btn--primary">Simpan Pengaturan</button>
      </form>
    </div>
  </div>

  <?php if ($botToken): ?>
  <!-- Webhook Sync -->
  <div class="card">
    <div class="card__header">
      <div class="card__title" style="display:flex;align-items:center;gap:8px">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
        Sinkronisasi Webhook
      </div>
    </div>
    <div class="card__body">
      <p class="form-hint" style="margin-bottom:16px">
        Tekan tombol di bawah ini agar Telegram mengetahui URL Webhook sistem Anda. URL yang akan didaftarkan adalah:
      </p>
      <code style="font-size:12px;background:var(--c-bg);padding:8px;border-radius:6px;display:block;word-break:break-all;border:1px solid var(--c-border);margin-bottom:20px;color:var(--c-text)">
        <?= htmlspecialchars(rtrim($appUrl, '/') . '/webhook.php?secret=' . $webhookSecret) ?>
      </code>
      <form method="POST">
        <input type="hidden" name="action" value="sync">
        <button type="submit" class="btn" style="background:#10b981;color:#fff;border:none">Sync Webhook Sekarang</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
