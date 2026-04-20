<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

use App\Config;
use App\TelegramBot;

// Note: In a real production setup, this file should be protected by a password
// or deleted after use. Since this is just a quick recovery tool, we leave it simple.
// Only accessible if setup is complete.
if (!Config::isSetupComplete()) {
    header('Location: /setup.php'); exit;
}

$token   = Config::get('telegram_bot_token', Config::env('TELEGRAM_BOT_TOKEN', ''));
$secret  = Config::get('telegram_webhook_secret', Config::env('TELEGRAM_WEBHOOK_SECRET', ''));
$siteUrl = Config::get('site_url', Config::env('APP_URL', ''));
$webhook = rtrim($siteUrl, '/') . '/webhook.php';

$bot = new TelegramBot($token);

$result = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync'])) {
    // If user provides a new URL, save it
    if (!empty($_POST['new_url'])) {
        $siteUrl = rtrim($_POST['new_url'], '/');
        $webhook = $siteUrl . '/webhook.php';
        if (isset($pdo)) {
            Config::set($pdo, 'site_url', $siteUrl);
        }
        Config::writeEnv(['APP_URL' => $siteUrl]);
    }

    $result = $bot->setWebhook($webhook, $secret);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sync Webhook — Google AI Pro</title>
<link rel="stylesheet" href="assets/css/main.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--c-bg-alt); padding:24px; }
  .card { background:#fff; padding:32px; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.05); max-width:500px; width:100%; border:1px solid var(--c-border); }
  .card h2 { margin-bottom:16px; display:flex; align-items:center; gap:8px; }
  .info { font-size:14px; color:var(--c-text-sec); margin-bottom:24px; line-height:1.5; }
  pre { background:var(--c-bg-alt); padding:12px; border-radius:8px; font-size:13px; overflow-x:auto; margin-bottom:24px; border:1px solid var(--c-border); }
  .success { background: #e6f4ea; color: #1e8e3e; padding:16px; border-radius:8px; margin-bottom:24px; border:1px solid #ceead6; }
  .error { background: #fce8e6; color: #d93025; padding:16px; border-radius:8px; margin-bottom:24px; border:1px solid #fad2cf; }
</style>
</head>
<body>
  <div class="card fade-up">
    <h2>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.92-10.26l5.08-5.08"/></svg>
      Sync Telegram Webhook
    </h2>
    <div class="info">
      Jika bot admin tidak merespons, kemungkinan webhook terputus atau URL berubah. Gunakan halaman ini untuk menautkan ulang webhook bot Telegram ke website.
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
      <?php if (!empty($result['ok'])): ?>
        <div class="success">
          <strong>✅ Berhasil disinkronisasi!</strong><br>
          <div style="margin-top:8px;font-size:13px"><?= htmlspecialchars($result['description'] ?? 'Webhook was set') ?></div>
        </div>
      <?php else: ?>
        <div class="error">
          <strong>❌ Gagal mengatur webhook!</strong><br>
          <div style="margin-top:8px;font-size:13px"><?= htmlspecialchars($result['description'] ?? 'Unknown error') ?></div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">URL Website Saat Ini</label>
        <input type="text" name="new_url" class="form-control" value="<?= htmlspecialchars($siteUrl) ?>" required>
      </div>
      
      <div class="form-group">
        <label class="form-label">Target Webhook Endpoint</label>
        <pre><?= htmlspecialchars($webhook) ?></pre>
      </div>

      <button type="submit" name="sync" value="1" class="btn btn--primary btn--full btn--lg">
        Sinkronisasi Sekarang
      </button>
      
      <div style="text-align:center;margin-top:16px">
        <a href="/" class="btn btn--ghost">Kembali ke Beranda</a>
      </div>
    </form>
  </div>
</body>
</html>
