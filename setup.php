<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
use App\Config;
$siteTitle   = Config::get('site_title', 'Google AI Pro');
$favicon     = Config::get('favicon_file', '');

use App\Config;
use App\Database;
use App\TelegramBot;

// If already setup, redirect to home
if (Config::isSetupComplete()) {
    header('Location: /'); exit;
}

$step   = (int) ($_GET['step'] ?? 1);
$errors = [];
$ok     = [];

// -----------------------------------------------------------------------
// POST handlers
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- AJAX: Test DB connection ---
    if ($action === 'test_db') {
        header('Content-Type: application/json');
        $cfg = [
            'host'     => trim($_POST['db_host']     ?? '127.0.0.1'),
            'port'     => trim($_POST['db_port']     ?? '3306'),
            'database' => trim($_POST['db_database'] ?? 'googlepro'),
            'username' => trim($_POST['db_username'] ?? 'root'),
            'password' => trim($_POST['db_password'] ?? ''),
        ];
        echo json_encode(['ok' => Database::testConnection($cfg)]);
        exit;
    }

    // --- AJAX: Test Telegram token ---
    if ($action === 'test_telegram') {
        header('Content-Type: application/json');
        $token   = trim($_POST['tg_token'] ?? '');
        $bot     = new TelegramBot($token);
        $result  = $bot->call('getMe', []);
        if (!empty($result['ok'])) {
            echo json_encode(['ok' => true, 'username' => '@' . $result['result']['username'], 'name' => $result['result']['first_name']]);
        } else {
            echo json_encode(['ok' => false]);
        }
        exit;
    }

    // --- Step 1: Save DB config ---
    if ($action === 'save_step1') {
        $cfg = [
            'host'     => trim($_POST['db_host']     ?? '127.0.0.1'),
            'port'     => trim($_POST['db_port']     ?? '3306'),
            'database' => trim($_POST['db_database'] ?? 'googlepro'),
            'username' => trim($_POST['db_username'] ?? 'root'),
            'password' => trim($_POST['db_password'] ?? ''),
        ];
        if (empty($cfg['database'])) $errors[] = 'Nama database tidak boleh kosong.';
        if (empty($errors)) {
            if (!Database::testConnection($cfg)) {
                $errors[] = 'Tidak bisa terhubung ke MySQL. Cek host/user/password.';
            } else {
                Config::writeEnv([
                    'DB_HOST'     => $cfg['host'],
                    'DB_PORT'     => $cfg['port'],
                    'DB_DATABASE' => $cfg['database'],
                    'DB_USERNAME' => $cfg['username'],
                    'DB_PASSWORD' => $cfg['password'],
                ]);
                // Install schema
                Database::reset();
                $pdo = Database::connect($cfg);
                Database::runSchema($pdo, __DIR__ . '/install/schema.sql');

                header('Location: setup.php?step=2'); exit;
            }
        }
    }

    // --- Step 2: Save Telegram config ---
    if ($action === 'save_step2') {
        $token      = trim($_POST['tg_token']   ?? '');
        $chatId     = trim($_POST['tg_chat_id'] ?? '');
        $secret     = trim($_POST['tg_secret']  ?? bin2hex(random_bytes(16)));
        $siteUrl    = rtrim(trim($_POST['site_url'] ?? ''), '/');

        if (empty($token))   $errors[] = 'Bot token tidak boleh kosong.';
        if (empty($chatId))  $errors[] = 'Admin Chat ID tidak boleh kosong.';
        if (empty($siteUrl)) $errors[] = 'URL website tidak boleh kosong.';

        if (empty($errors)) {
            // Verify token
            $bot    = new TelegramBot($token);
            $result = $bot->call('getMe', []);
            if (empty($result['ok'])) {
                $errors[] = 'Token Telegram tidak valid.';
            } else {
                // Set webhook
                $webhookUrl = $siteUrl . '/webhook.php';
                $bot->setWebhook($webhookUrl, $secret);

                Config::writeEnv([
                    'TELEGRAM_BOT_TOKEN'       => $token,
                    'TELEGRAM_ADMIN_CHAT_ID'   => $chatId,
                    'TELEGRAM_WEBHOOK_SECRET'  => $secret,
                    'APP_URL'                  => $siteUrl,
                ]);

                // Save to DB too
                Database::reset();
                Config::init(BASE_PATH);
                $pdo = Database::connect(Config::db());
                Config::loadFromDb($pdo);
                Config::set($pdo, 'telegram_bot_token',      $token);
                Config::set($pdo, 'telegram_admin_chat_id',  $chatId);
                Config::set($pdo, 'telegram_webhook_secret', $secret);
                Config::set($pdo, 'site_url',                $siteUrl);

                // Send test message
                $bot->sendMessage((int) $chatId,
                    "✅ <b>Webhook berhasil terhubung!</b>\n\nBot admin Google AI Pro siap digunakan.\nKetik /start untuk memulai.");

                header('Location: setup.php?step=3'); exit;
            }
        }
    }

    // --- Step 3: Save product & finalize ---
    if ($action === 'save_step3') {
        $price    = (int) preg_replace('/\D/', '', $_POST['price'] ?? '309000');
        $googleId = trim($_POST['google_client_id']     ?? '');
        $googleSc = trim($_POST['google_client_secret'] ?? '');

        if ($price < 1000) $errors[] = 'Harga minimal Rp 1.000';

        if (empty($errors)) {
            // Save env
            if ($googleId && $googleSc) {
                Config::writeEnv([
                    'GOOGLE_CLIENT_ID'     => $googleId,
                    'GOOGLE_CLIENT_SECRET' => $googleSc,
                ]);
            }

            // Save to DB and mark setup complete
            Database::reset(); Config::init(BASE_PATH);
            $pdo = Database::connect(Config::db());
            Config::loadFromDb($pdo);
            Config::set($pdo, 'product_price',    (string) $price);
            Config::set($pdo, 'google_client_id', $googleId);
            Config::set($pdo, 'setup_complete',   '1');

            header('Location: setup.php?step=done'); exit;
        }
    }
}

$stepLabels = ['Database', 'Telegram', 'Produk'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup — Google AI Pro</title>
<link rel="stylesheet" href="assets/css/main.css">
<link rel="stylesheet" href="assets/css/setup.css">
</head>
<body>
<div class="setup-page">
<div class="setup-card fade-up">

  <!-- Header -->
  <div class="setup-header">
    <div class="setup-header__logo">
      <svg viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" fill="#4285f4"/>
        <path d="M12 7v5l3 3" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Google AI Pro Setup
    </div>
  </div>

  <?php if ($step === 1 || $step === 2 || $step === 3): ?>
  <!-- Steps indicator -->
  <div class="setup-steps">
    <?php foreach ($stepLabels as $i => $label): 
      $n = $i + 1;
      $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
    ?>
    <div class="setup-step <?= $cls ?>">
      <div class="setup-step__num"><?= $n < $step ? '✓' : $n ?></div>
      <span class="setup-step__label"><?= $label ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php foreach ($errors as $e): ?>
  <div style="padding:0 40px;"><div class="alert alert--error">⚠ <?= htmlspecialchars($e) ?></div></div>
  <?php endforeach; ?>

  <!-- ===== STEP 1: DATABASE ===== -->
  <?php if ($step === 1): ?>
  <div class="setup-body">
    <div class="setup-section-title">Konfigurasi Database</div>
    <div class="setup-section-sub">Masukkan kredensial MySQL Anda. Database akan dibuat otomatis.</div>
    <form method="POST" id="form-step1">
      <input type="hidden" name="action" value="save_step1">
      <div class="form-group">
        <label class="form-label">Host</label>
        <input class="form-control" name="db_host" id="db_host" value="127.0.0.1" placeholder="127.0.0.1">
      </div>
      <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
        <div class="form-group">
          <label class="form-label">Nama Database</label>
          <input class="form-control" name="db_database" id="db_database" value="googlepro" placeholder="googlepro">
        </div>
        <div class="form-group">
          <label class="form-label">Port</label>
          <input class="form-control" name="db_port" value="3306" placeholder="3306">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input class="form-control" name="db_username" id="db_username" value="root" placeholder="root">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-control" name="db_password" id="db_password" type="password" placeholder="(kosong jika tidak ada)">
        </div>
      </div>
      <div class="conn-status" id="conn-status">
        <span class="dot"></span><span id="conn-msg">Klik "Test Koneksi" untuk memeriksa</span>
      </div>
    </form>
  </div>
  <div class="setup-footer">
    <button type="button" class="btn btn--ghost" onclick="testDb()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3a4.237 4.237 0 00-6 0zm-4-4l2 2a7.074 7.074 0 0110 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg>
      Test Koneksi
    </button>
    <button type="submit" form="form-step1" class="btn btn--primary">Lanjut →</button>
  </div>

  <!-- ===== STEP 2: TELEGRAM ===== -->
  <?php elseif ($step === 2): ?>
  <div class="setup-body">
    <div class="setup-section-title">Integrasi Telegram Bot</div>
    <div class="setup-section-sub">Buat bot via @BotFather lalu masukkan token & chat ID Anda.</div>
    <form method="POST" id="form-step2">
      <input type="hidden" name="action" value="save_step2">
      <div class="form-group">
        <label class="form-label">URL Website</label>
        <input class="form-control" name="site_url" id="site_url" value="<?= htmlspecialchars(Config::env('APP_URL','')) ?>" placeholder="https://yourdomain.com/googlepro">
        <div class="form-hint">URL publik tanpa trailing slash. Webhook akan di-set ke {URL}/webhook.php</div>
      </div>
      <div class="form-group">
        <label class="form-label">Bot Token</label>
        <input class="form-control" name="tg_token" id="tg_token" placeholder="123456789:ABCdef..." autocomplete="off">
        <div class="form-hint">Dapatkan dari <a href="https://t.me/botfather" target="_blank">@BotFather</a></div>
      </div>
      <div class="form-group">
        <label class="form-label">Admin Chat ID</label>
        <input class="form-control" name="tg_chat_id" id="tg_chat_id" placeholder="123456789">
        <div class="form-hint">Chat ID Anda. Cari lewat <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a></div>
      </div>
      <div id="tg-preview" style="display:none">
        <div class="tg-preview">
          <div class="tg-preview__avatar">🤖</div>
          <div class="tg-preview__info">
            <div class="tg-preview__name" id="tg-name">-</div>
            <div class="tg-preview__id" id="tg-username">-</div>
          </div>
        </div>
      </div>
    </form>
  </div>
  <div class="setup-footer">
    <button type="button" class="btn btn--ghost" onclick="testTelegram()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12l-5 2.5V14H7V8h6v2.5L18 8v6z"/></svg>
      Test Bot
    </button>
    <button type="submit" form="form-step2" class="btn btn--primary">Lanjut →</button>
  </div>

  <!-- ===== STEP 3: PRODUCT ===== -->
  <?php elseif ($step === 3): ?>
  <div class="setup-body">
    <div class="setup-section-title">Konfigurasi Produk</div>
    <div class="setup-section-sub">Atur harga dan login Google SSO (opsional).</div>
    <form method="POST" id="form-step3">
      <input type="hidden" name="action" value="save_step3">
      <div class="form-group">
        <label class="form-label">Harga Produk (IDR)</label>
        <input class="form-control" name="price" value="309000" placeholder="309000">
        <div class="form-hint">Harga dalam Rupiah, tanpa titik/koma</div>
      </div>
      <div class="divider">Google SSO (Opsional)</div>
      <div class="alert alert--info" style="margin-bottom:16px">
        ℹ️ Lewati bagian ini jika belum punya Google OAuth credentials. Bisa diisi nanti via Telegram.
      </div>
      <div class="form-group">
        <label class="form-label">Google Client ID</label>
        <input class="form-control" name="google_client_id" placeholder="xxx.apps.googleusercontent.com">
      </div>
      <div class="form-group">
        <label class="form-label">Google Client Secret</label>
        <input class="form-control" name="google_client_secret" type="password" placeholder="GOCSPX-...">
      </div>
    </form>
  </div>
  <div class="setup-footer">
    <a href="setup.php?step=2" class="btn btn--ghost">← Kembali</a>
    <button type="submit" form="form-step3" class="btn btn--primary">Selesai ✓</button>
  </div>

  <!-- ===== DONE ===== -->
  <?php elseif ($step === 101 || isset($_GET['step']) && $_GET['step'] === 'done'): ?>
  <div class="setup-success">
    <div class="setup-success__icon">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#34a853" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="setup-success__title">Setup Berhasil!</div>
    <div class="setup-success__sub">
      Semua konfigurasi telah tersimpan. Bot Telegram sudah aktif dan siap menerima perintah admin.
    </div>
    <a href="/" class="btn btn--primary btn--lg">Lihat Website →</a>
  </div>
  <?php endif; ?>

</div><!-- /.setup-card -->
</div><!-- /.setup-page -->

<script>
async function testDb() {
  const st  = document.getElementById('conn-status');
  const msg = document.getElementById('conn-msg');
  st.className = 'conn-status loading';
  msg.textContent = 'Menghubungkan...';

  const fd = new FormData();
  fd.append('action', 'test_db');
  fd.append('db_host',     document.querySelector('[name=db_host]').value);
  fd.append('db_port',     document.querySelector('[name=db_port]').value);
  fd.append('db_database', document.querySelector('[name=db_database]').value);
  fd.append('db_username', document.querySelector('[name=db_username]').value);
  fd.append('db_password', document.querySelector('[name=db_password]').value);

  try {
    const res  = await fetch('setup.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      st.className = 'conn-status success';
      msg.textContent = 'Koneksi berhasil! Database siap.';
    } else {
      st.className = 'conn-status error';
      msg.textContent = 'Gagal terhubung. Periksa kredensial.';
    }
  } catch(e) {
    st.className = 'conn-status error';
    msg.textContent = 'Error: ' + e.message;
  }
}

async function testTelegram() {
  const token = document.getElementById('tg_token').value.trim();
  if (!token) { alert('Masukkan token terlebih dahulu.'); return; }

  const fd = new FormData();
  fd.append('action', 'test_telegram');
  fd.append('tg_token', token);

  try {
    const res  = await fetch('setup.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('tg-name').textContent     = data.name;
      document.getElementById('tg-username').textContent = data.username;
      document.getElementById('tg-preview').style.display = 'block';
    } else {
      alert('Token tidak valid. Periksa kembali.');
    }
  } catch(e) {
    alert('Error: ' + e.message);
  }
}
</script>
</body>
</html>
