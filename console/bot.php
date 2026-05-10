<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

$pageTitle  = 'Telegram Bot';
$activePage = 'bot';

$msg = '';
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$chatId = $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? '';
$appUrl = $_ENV['APP_URL'] ?? 'https://'.$_SERVER['HTTP_HOST'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Selalu ambil dari input post untuk menghindari "kosong" saat error
    $botToken = trim($_POST['bot_token'] ?? '');
    $chatId = trim($_POST['chat_id'] ?? '');

    // 1. Simpan ke .env dulu apapun actionnya
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        $env = file_get_contents($envFile);
        $env = preg_replace('/^TELEGRAM_BOT_TOKEN=.*$/m', 'TELEGRAM_BOT_TOKEN=' . $botToken, $env);
        if (strpos($env, 'TELEGRAM_BOT_TOKEN=') === false) $env .= "\nTELEGRAM_BOT_TOKEN=" . $botToken;
        
        $env = preg_replace('/^TELEGRAM_ADMIN_CHAT_ID=.*$/m', 'TELEGRAM_ADMIN_CHAT_ID=' . $chatId, $env);
        if (strpos($env, 'TELEGRAM_ADMIN_CHAT_ID=') === false) $env .= "\nTELEGRAM_ADMIN_CHAT_ID=" . $chatId;
        
        // Ensure secret line is removed if it existed
        $env = preg_replace('/^TELEGRAM_WEBHOOK_SECRET=.*$\n?/m', '', $env);
        
        file_put_contents($envFile, $env);
    }
    
    // Set ulang ke _ENV agar tidak kosong
    $_ENV['TELEGRAM_BOT_TOKEN'] = $botToken;
    $_ENV['TELEGRAM_ADMIN_CHAT_ID'] = $chatId;
    
    $msg = "Pengaturan Bot berhasil disimpan!";
    $msgType = 'success';

    // 2. Jika action adalah sync, lakukan request cURL
    if ($action === 'sync') {
        if (empty($botToken)) {
            $msg = "Bot Token kosong. Simpan token terlebih dahulu.";
            $msgType = 'error';
        } else {
            $url = "https://api.telegram.org/bot$botToken/setWebhook";
            $webhookUrl = rtrim($appUrl, '/') . '/webhook.php';
            
            $ch = curl_init($url . "?url=" . urlencode($webhookUrl));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix untuk Laragon localhost cert issue
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($response !== false) {
                $res = json_decode($response, true);
                if ($res && !empty($res['ok'])) {
                    $msg = "Pengaturan disimpan dan Webhook berhasil disinkronisasi!";
                    $msgType = 'success';
                } else {
                    $msg = "Pengaturan tersimpan, tapi sinkronisasi gagal: " . htmlspecialchars($res['description'] ?? '');
                    $msgType = 'error';
                }
            } else {
                $msg = "Pengaturan tersimpan, tapi gagal menghubungi API Telegram. Error: " . htmlspecialchars($error);
                $msgType = 'error';
            }
        }
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
        <div style="display:flex;gap:12px;margin-top:10px">
          <button type="submit" name="action" value="save" class="btn btn--primary">Simpan Pengaturan</button>
          <?php if ($botToken): ?>
          <button type="submit" name="action" value="sync" class="btn" style="background:#10b981;color:#fff;border:none">Simpan & Sync Webhook</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <?php if ($botToken): ?>
  <!-- Webhook Settings Info -->
  <div class="card">
    <div class="card__header">
      <div class="card__title" style="display:flex;align-items:center;gap:8px">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
        Status Webhook
      </div>
    </div>
    <div class="card__body">
      <p class="form-hint" style="margin-bottom:16px">
        URL webhook rahasia Anda sudah diset untuk menerima pembaruan secara otomatis dari Telegram.
      </p>
      <code style="font-size:12px;background:var(--c-bg);padding:8px;border-radius:6px;display:block;word-break:break-all;border:1px solid var(--c-border);margin-bottom:10px;color:var(--c-text)">
        <?= htmlspecialchars(rtrim($appUrl, '/') . '/webhook.php') ?>
      </code>
      <p class="form-hint" style="font-size:12px">
        Gunakan tombol <strong>Simpan & Sync Webhook</strong> di form sebelah kiri jika Anda ingin melakukan reset koneksi dengan bot.
      </p>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
