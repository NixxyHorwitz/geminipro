<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
use App\Config;

$headerTitle = Config::get('product_name', 'Google AI Pro');
$siteTitle   = Config::get('site_title', $headerTitle);
$favicon     = Config::get('favicon_file', '');

// Error logging — write to error_log file in project root for easy debugging
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log.txt');
// Keep display_errors OFF in production — log only
ini_set('display_errors', '0');

use App\Order;
use App\Logger;
use App\QrisHelper;

if (!Config::isSetupComplete()) {
    header('Location: /setup.php'); exit;
}

if (!$pdo) {
    error_log('[checkout.php] FATAL: $pdo is null — DB connection failed');
    http_response_code(500);
    die('<h1>Server Error</h1><p>Koneksi database bermasalah. Silakan coba beberapa saat lagi.</p>');
}

$logger = new Logger($pdo, null, 0);
$order  = new Order($pdo);

$price    = (int) Config::get('product_price', 309000);
$priceStr = 'Rp ' . number_format($price, 0, ',', '.');
$step     = (int) ($_GET['step'] ?? 1);
$errors   = [];

// -----------------------------------------------------------------------
// Get active QRIS template
// -----------------------------------------------------------------------
function getQrisTemplate(\PDO $pdo): ?array {
    try {
        $r = $pdo->query("SELECT * FROM qris_templates WHERE active=1 ORDER BY id DESC LIMIT 1")->fetch();
        return $r ?: null;
    } catch (\Exception $e) {
        error_log('[checkout.php][getQrisTemplate] ' . $e->getMessage());
        return null;
    }
}

// -----------------------------------------------------------------------
// AJAX: Generate QRIS image
// -----------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'gen_qris') {
    header('Content-Type: application/json');
    $code = trim($_POST['order_code'] ?? '');
    if (!$code) { echo json_encode(['ok'=>false, 'msg'=>'Kode order tidak valid']); exit; }

    try {
        $ord = $order->findByCode($code);
        if (!$ord) { echo json_encode(['ok'=>false, 'msg'=>'Order tidak ditemukan']); exit; }

        $tpl = getQrisTemplate($pdo);
        if (!$tpl) {
            echo json_encode(['ok'=>false, 'msg'=>'QRIS belum dikonfigurasi. Hubungi admin.']); exit;
        }

        $dynamicQris = QrisHelper::setAmount($tpl['raw_string'], (int) $ord['amount']);
        $img         = QrisHelper::generateQrImage($dynamicQris, 280);

        if (!$img) {
            error_log('[checkout.php][gen_qris] generateQrImage returned empty — external API failed');
            echo json_encode(['ok'=>false, 'msg'=>'Gagal generate QR. Coba refresh halaman.']); exit;
        }

        echo json_encode(['ok' => true, 'img' => $img, 'qris' => $dynamicQris]);
    } catch (\Throwable $e) {
        error_log('[checkout.php][gen_qris] Exception: ' . $e->getMessage());
        echo json_encode(['ok'=>false, 'msg'=>'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// -----------------------------------------------------------------------
// AJAX: check status endpoint
// -----------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json');
    $code = trim($_GET['order'] ?? '');
    $ord  = $code ? $order->findByCode($code) : null;
    if ($ord) {
        echo json_encode(['status' => $ord['status'], 'reason' => $ord['rejected_reason']]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
    exit;
}

// -----------------------------------------------------------------------
// POST Step 1: Create order
// -----------------------------------------------------------------------
$newOrder = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    $method = $_POST['method'] ?? '';
    $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if (!in_array($method, ['sso','link'])) $errors[] = 'Pilih metode aktivasi.';
    if (!$email) $errors[] = 'Masukkan email yang valid.';

    if (empty($errors)) {
        try {
            $data = [
                'email'            => $email,
                'method'           => $method,
                'sso_email'        => $method === 'sso'  ? $email : null,
                'activation_email' => $method === 'link' ? $email : null,
                'amount'           => $price,
                'ip_address'       => \App\Logger::getIp(),
                'user_agent'       => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];
            $newOrder = $order->create($data);
            $logger->log('/checkout', 'order_created', ['order_code' => $newOrder['order_code']]);

            // Redirect to step 2
            header("Location: checkout.php?step=2&order={$newOrder['order_code']}"); exit;
        } catch (\Throwable $e) {
            error_log('[checkout.php][create_order] Exception: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan server. Coba lagi dalam beberapa saat.';
        }
    }
}

// -----------------------------------------------------------------------
// Step 2: Load existing order
// -----------------------------------------------------------------------
$currentOrder = null;
if ($step === 2) {
    $code = trim($_GET['order'] ?? '');
    if ($code) $currentOrder = $order->findByCode($code);
    if (!$currentOrder || $currentOrder['status'] === 'expired') {
        header('Location: checkout.php?step=1&err=expired'); exit;
    }
    if ($currentOrder['status'] === 'confirmed') {
        header("Location: checkout.php?step=done&order={$code}"); exit;
    }
    $logger->log('/checkout', 'payment_view', ['order_code' => $code]);
}

// Step done
$doneOrder = null;
if ($step === 3 || isset($_GET['step']) && $_GET['step'] === 'done') {
    $code      = trim($_GET['order'] ?? '');
    $doneOrder = $code ? $order->findByCode($code) : null;
    $step      = 3;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Checkout — Google AI Pro</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="stylesheet" href="assets/css/checkout.css">
</head>
<body class="checkout-page">

<!-- Mini header -->
<header class="header">
  <a href="/" class="header__logo">
    <svg viewBox="0 0 32 32" fill="none">
      <path d="M28 16.3C28 9.5 22.6 4 16 4C9.4 4 4 9.5 4 16C4 22.5 9.4 28 16 28H28V16.3Z" fill="#4285F4"/>
      <path d="M22 16H16V22H22V16Z" fill="white"/>
      <path d="M16 10H10V16H16V10Z" fill="white"/>
      <path d="M22 10H16V16H22V10Z" fill="#FBBC04"/>
    </svg>
    Google AI Pro
  </a>
  <nav class="header__nav">
    <a href="/" class="btn btn--ghost btn--sm">← Kembali</a>
  </nav>
</header>

<div style="max-width:960px;margin:0 auto;padding:0 0 40px">
<div class="checkout-layout">

  <!-- ============================================================ -->
  <!-- LEFT: Main Content                                           -->
  <!-- ============================================================ -->
  <div class="checkout-main">

    <!-- Step tabs -->
    <div class="checkout-steps">
      <div class="checkout-step-tab <?= $step===1?'active':($step>1?'done':'') ?>">1. Aktivasi</div>
      <div class="checkout-step-tab <?= $step===2?'active':($step>2?'done':'') ?>">2. Pembayaran</div>
      <div class="checkout-step-tab <?= $step===3?'active':'' ?>">3. Selesai</div>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert--error">⚠ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <!-- ===== STEP 1: Pilih metode ===== -->
    <?php if ($step === 1): ?>
    <div class="step-panel active" id="panel-1">
      <div class="checkout-section-title">Pilih Metode Aktivasi</div>
      <div class="checkout-section-sub">Bagaimana Anda ingin mengaktifkan Google AI Pro?</div>

      <form method="POST" id="form-checkout">
        <input type="hidden" name="action" value="create_order">
        <input type="hidden" name="method" id="method-input" value="">

        <div class="method-cards" id="method-cards">
          <div class="method-card" data-method="sso" onclick="selectMethod('sso',this)">
            <div class="method-card__icon">🔐</div>
            <div class="method-card__title">Login SSO</div>
            <div class="method-card__sub">Login dengan akun Google Anda langsung</div>
          </div>
          <div class="method-card" data-method="link" onclick="selectMethod('link',this)">
            <div class="method-card__icon">📧</div>
            <div class="method-card__title">Link Aktivasi</div>
            <div class="method-card__sub">Terima link undangan di email Anda</div>
          </div>
        </div>

        <div id="email-section" style="display:none;animation:fadeUp .3s ease both">
          <div class="form-group">
            <label class="form-label" id="email-label">Email Google Anda</label>
            <input class="form-control" type="email" name="email" id="email-input"
                   placeholder="nama@gmail.com" required autocomplete="email">
            <div class="form-hint" id="email-hint">Pastikan ini adalah akun Google yang aktif</div>
          </div>
          <button type="submit" class="btn btn--primary btn--full btn--lg" id="btn-next">
            Lanjut ke Pembayaran →
          </button>
        </div>
      </form>
    </div>

    <!-- ===== STEP 2: Payment ===== -->
    <?php elseif ($step === 2 && $currentOrder): ?>
    <div class="step-panel active" id="panel-2">
      <div class="checkout-section-title">Pembayaran QRIS</div>
      <div class="checkout-section-sub">
        Scan QR di bawah menggunakan e-wallet Anda. Jangan ubah nominal.
      </div>

      <!-- Timer -->
      <div class="timer-bar" id="timer-bar">
        ⏱ Selesaikan pembayaran dalam:
        <span class="timer-bar__time" id="countdown">15:00</span>
      </div>

      <!-- QRIS Display -->
      <div class="qris-container">
        <div class="qris-frame" id="qris-frame">
          <div style="width:220px;height:220px;display:flex;align-items:center;justify-content:center;color:var(--c-text-hint);flex-direction:column;gap:8px">
            <div class="spinner"></div>
            <div style="font-size:12px">Memuat QR Code...</div>
          </div>
        </div>
        <div class="qris-logo">
          <div class="qris-badge">QRIS</div>
          Nasional &middot; Semua E-Wallet
        </div>
        <div class="qris-amount" id="qris-amount"><?= $priceStr ?></div>
        <div class="qris-amount-label">Total yang harus dibayar</div>
      </div>

      <ul class="payment-steps">
        <li><span class="step-num">1</span>Buka aplikasi e-wallet Anda (GoPay, OVO, DANA, dll)</li>
        <li><span class="step-num">2</span>Pilih &ldquo;Scan QR&rdquo; atau &ldquo;Pay&rdquo;</li>
        <li><span class="step-num">3</span>Scan QR Code di atas</li>
        <li><span class="step-num">4</span>Pastikan nominal sesuai, lalu konfirmasi pembayaran</li>
        <li><span class="step-num">5</span>Tunggu konfirmasi dari admin (biasanya 1&ndash;5 menit)</li>
      </ul>

      <div class="alert alert--warn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;margin-top:1px"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
        Jika sudah bayar namun belum dikonfirmasi dalam 15 menit, hubungi admin via Telegram dengan kode order Anda.
      </div>

      <div style="text-align:center;margin-top:12px">
        <div style="font-size:13px;color:var(--c-text-sec);margin-bottom:8px">Kode Order Anda:</div>
        <code style="font-size:18px;font-weight:700;color:var(--c-text-primary);letter-spacing:.1em">
          <?= htmlspecialchars($currentOrder['order_code']) ?>
        </code>
      </div>

      <div style="margin-top:24px">
        <button onclick="checkStatus()" class="btn btn--outline btn--full" id="btn-check">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
          Cek Status Pembayaran
        </button>
      </div>
    </div>

    <!-- ===== STEP 3: Done ===== -->
    <?php elseif ($step === 3): ?>
    <div class="step-panel active" id="panel-3">
      <?php if ($doneOrder && $doneOrder['status'] === 'confirmed'): ?>
      <div class="payment-success">
        <div class="payment-success__icon" style="background:var(--c-green-light)">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#34a853" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2 style="margin-bottom:8px">Pembayaran Dikonfirmasi!</h2>
        <p style="color:var(--c-text-sec);margin-bottom:24px">
          Terima kasih! Link aktivasi Google AI Pro telah dikirim ke email Anda.
        </p>
        <div style="background:var(--c-green-light);border:1px solid #a8d5b5;border-radius:var(--radius-md);padding:20px;text-align:left;margin-bottom:24px">
          <div style="font-size:13px;color:var(--c-text-sec);margin-bottom:4px">Email tujuan aktivasi:</div>
          <div style="font-size:16px;font-weight:600"><?= htmlspecialchars($doneOrder['email']) ?></div>
        </div>
        <a href="/" class="btn btn--primary btn--lg">Kembali ke Beranda</a>
      </div>
      <?php else: ?>
      <div class="payment-success">
        <div class="payment-success__icon" style="background:#fff8e1">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#f29900" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h2 style="margin-bottom:8px">Menunggu Konfirmasi</h2>
        <p style="color:var(--c-text-sec);margin-bottom:24px">
          Pembayaran Anda sedang diverifikasi oleh admin. Proses biasanya 1–5 menit.
        </p>
        <?php if ($doneOrder): ?>
        <div style="background:var(--c-blue-light);border:1px solid #c6d9f8;border-radius:var(--radius-md);padding:20px;margin-bottom:24px">
          <div style="font-size:13px;color:var(--c-text-sec)">Kode Order:</div>
          <code style="font-size:20px;font-weight:700;color:var(--c-blue)"><?= htmlspecialchars($doneOrder['order_code']) ?></code>
        </div>
        <?php endif; ?>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
          <button onclick="checkStatus()" class="btn btn--primary">Refresh Status</button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /checkout-main -->

  <!-- ============================================================ -->
  <!-- RIGHT: Order Summary                                         -->
  <!-- ============================================================ -->
  <div class="checkout-summary">
    <div class="checkout-summary__title">Ringkasan Order</div>

    <div class="order-product">
      <div class="order-product__icon">🤖</div>
      <div>
        <div class="order-product__name">Google AI Pro</div>
        <div class="order-product__dur">Paket 12 Bulan</div>
      </div>
    </div>

    <div class="order-line">
      <span class="order-line__label">Harga per bulan</span>
      <span class="order-line__value"><?= $priceStr ?></span>
    </div>
    <div class="order-line">
      <span class="order-line__label">Promo bulan pertama</span>
      <span class="order-line__value" style="color:var(--c-green)">Rp 0</span>
    </div>
    <div class="order-line">
      <span class="order-line__label">Durasi</span>
      <span class="order-line__value">12 bulan</span>
    </div>
    <div class="order-line">
      <span class="order-line__label">Pajak</span>
      <span class="order-line__value">Termasuk</span>
    </div>
    <div class="order-total">
      <span>Total</span>
      <span class="order-total__price"><?= $priceStr ?></span>
    </div>

    <div class="checkout-trust">
      <div class="checkout-trust-item">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        Pembayaran 100% Aman
      </div>
      <div class="checkout-trust-item">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        Garansi Uang Kembali 7 Hari
      </div>
      <div class="checkout-trust-item">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        Konfirmasi 1&ndash;5 Menit
      </div>
      <div class="checkout-trust-item">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        Semua E-Wallet QRIS
      </div>
    </div>

    <!-- Accepted wallets -->
    <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--c-border)">
      <div style="font-size:11px;font-weight:600;color:var(--c-text-hint);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Diterima via QRIS</div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;font-size:12px;color:var(--c-text-sec)">
        <?php foreach (['GoPay','OVO','DANA','ShopeePay','LinkAja','BCA','BRI','BNI','Mandiri','BSI'] as $w): ?>
        <span style="background:var(--c-bg-alt);border:1px solid var(--c-border);border-radius:4px;padding:3px 8px"><?= $w ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div><!-- /checkout-summary -->

</div><!-- /checkout-layout -->
</div>

<script>
// ------- Step 1: Method selector -------
function selectMethod(method, el) {
  document.querySelectorAll('.method-card').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('method-input').value = method;
  document.getElementById('email-section').style.display = 'block';

  const label = document.getElementById('email-label');
  const hint  = document.getElementById('email-hint');
  if (method === 'sso') {
    label.textContent = 'Email Akun Google Anda';
    hint.textContent  = 'Email yang akan digunakan untuk login SSO';
  } else {
    label.textContent = 'Email Tujuan Aktivasi';
    hint.textContent  = 'Link undangan akan dikirim ke email ini';
  }
  document.getElementById('email-input').focus();
}

document.getElementById('form-checkout')?.addEventListener('submit', function(e) {
  if (!document.getElementById('method-input').value) {
    e.preventDefault();
    alert('Pilih metode aktivasi terlebih dahulu.');
  }
});

// ------- Step 2: Load QRIS -------
<?php if ($step === 2 && $currentOrder): ?>
const ORDER_CODE = '<?= htmlspecialchars($currentOrder['order_code']) ?>';
const EXPIRES_AT = <?= strtotime($currentOrder['expires_at']) ?>;

// Countdown timer
function updateTimer() {
  const now = Math.floor(Date.now() / 1000);
  const rem = EXPIRES_AT - now;
  const bar = document.getElementById('timer-bar');
  if (rem <= 0) {
    document.getElementById('countdown').textContent = '00:00';
    bar?.classList.add('urgent');
    return;
  }
  const m = String(Math.floor(rem / 60)).padStart(2, '0');
  const s = String(rem % 60).padStart(2, '0');
  document.getElementById('countdown').textContent = `${m}:${s}`;
  if (rem < 180) bar?.classList.add('urgent');
}
updateTimer();
setInterval(updateTimer, 1000);

// Load QRIS image
async function loadQris() {
  const fd = new FormData();
  fd.append('action', 'gen_qris');
  fd.append('order_code', ORDER_CODE);
  const res  = await fetch('checkout.php', { method: 'POST', body: fd });
  const data = await res.json();
  const frame = document.getElementById('qris-frame');
  if (data.ok) {
    frame.innerHTML = `<img src="${data.img}" alt="QRIS" width="220" height="220">`;
  } else {
    frame.innerHTML = `<div style="width:220px;height:220px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;color:var(--c-red);text-align:center;padding:16px">
      <span style="font-size:28px">⚠️</span>
      <span style="font-size:12px">${data.msg || 'QRIS belum tersedia. Hubungi admin.'}</span>
    </div>`;
  }
}
loadQris();

// Check payment status
async function checkStatus() {
  const btn = document.getElementById('btn-check');
  if (btn) { btn.disabled = true; btn.textContent = 'Mengecek...'; }
  try {
    const res  = await fetch(`checkout.php?action=check_status&order=${ORDER_CODE}`);
    const data = await res.json();
    if (data.status === 'confirmed') {
      window.location.href = `checkout.php?step=done&order=${ORDER_CODE}`;
    } else if (data.status === 'rejected') {
      alert('Pembayaran ditolak. Alasan: ' + (data.reason || 'Tidak ada keterangan'));
    } else {
      if (btn) { btn.disabled = false; btn.textContent = '🔄 Cek Status Pembayaran'; }
      alert('Pembayaran belum dikonfirmasi. Mohon tunggu admin memverifikasi.');
    }
  } catch(e) {
    if (btn) { btn.disabled = false; btn.textContent = '🔄 Cek Status Pembayaran'; }
  }
}

// Auto-check every 30s
setInterval(() => {
  fetch(`checkout.php?action=check_status&order=${ORDER_CODE}`)
    .then(r => r.json())
    .then(d => {
      if (d.status === 'confirmed') window.location.href = `checkout.php?step=done&order=${ORDER_CODE}`;
    }).catch(() => {});
}, 30000);
<?php endif; ?>

<?php if ($step === 3 && $doneOrder && $doneOrder['status'] !== 'confirmed'): ?>
// Auto-refresh for pending orders
function checkStatus() {
  window.location.reload();
}
setInterval(() => window.location.reload(), 15000);
<?php endif; ?>
</script>

</body>
</html>
