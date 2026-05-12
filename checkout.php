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
$uniqueFeeEnabled = Config::get('unique_fee_enabled', '0') === '1';
$step     = (int) ($_GET['step'] ?? 1);
$errors   = [];

// Initialize fee service in session for step 1 to display to user
if ($step === 1 && $uniqueFeeEnabled && !isset($_SESSION['checkout_fee'])) {
    $min = (int) Config::get('unique_fee_min', 1);
    $max = (int) Config::get('unique_fee_max', 999);
    if ($max >= $min) {
        $fee = 0;
        for ($i=0; $i<50; $i++) {
            $testFee = mt_rand($min, $max);
            $testAmount = $price + $testFee;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='pending' AND amount = ?");
            $stmt->execute([$testAmount]);
            if ($stmt->fetchColumn() == 0) {
                $fee = $testFee;
                break;
            }
        }
        if ($fee === 0) $fee = mt_rand($min, $max); // fallback
        $_SESSION['checkout_fee'] = $fee;
    }
}

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
    $method = 'link';
    $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if (!$email) $errors[] = 'Masukkan email yang valid.';

    if (empty($errors)) {
        try {
            $finalPrice = $price;
            if ($uniqueFeeEnabled) {
                $fee = $_SESSION['checkout_fee'] ?? 0;
                $min = (int) Config::get('unique_fee_min', 1);
                $max = (int) Config::get('unique_fee_max', 999);
                
                // Double check uniqueness just in case someone else took this amount
                if ($max >= $min) {
                    for ($i=0; $i<10; $i++) {
                        if ($fee <= 0) $fee = mt_rand($min, $max);
                        $testAmount = $price + $fee;
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='pending' AND amount = ?");
                        $stmt->execute([$testAmount]);
                        if ($stmt->fetchColumn() == 0) break;
                        $fee = 0; // force re-roll
                    }
                }
                $finalPrice += $fee;
            }
            unset($_SESSION['checkout_fee']);

            $data = [
                'email'            => $email,
                'method'           => $method,
                'sso_email'        => null,
                'activation_email' => $email,
                'amount'           => $finalPrice,
                'ip_address'       => \App\Logger::getIp(),
                'user_agent'       => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];
            $newOrder = $order->create($data);
            $logger->log('/checkout', 'order_created', ['order_code' => $newOrder['order_code']]);

            // Kirim notifikasi Telegram jika diaktifkan
            if (\App\Config::env('TELEGRAM_ORDER_NOTIF') === '1') {
                $botToken = \App\Config::env('TELEGRAM_BOT_TOKEN');
                $chatId = \App\Config::env('TELEGRAM_ADMIN_CHAT_ID');
                if ($botToken && $chatId) {
                    $text = "🔔 *PESANAN BARU (PENDING)*\n\n"
                          . "Order Code: `{$newOrder['order_code']}`\n"
                          . "Email: {$email}\n"
                          . "Nominal: Rp " . number_format($finalPrice, 0, ',', '.') . "\n\n"
                          . "Balas chat ini dengan `{$newOrder['order_code']}` untuk mengonfirmasi pembayaran jika saldo QRIS sudah masuk.";
                    
                    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                        'chat_id' => $chatId,
                        'text' => $text,
                        'parse_mode' => 'Markdown'
                    ]));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Jangan sampai checkout hang jika tele down
                    curl_exec($ch);
                    curl_close($ch);
                }
            }

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

// Calculate display prices for summary
$displayPrice = $price;
$feeAmount = 0;
if ($currentOrder) {
    $displayPrice = (int)$currentOrder['amount'];
    $feeAmount = $displayPrice - $price;
} elseif ($doneOrder) {
    $displayPrice = (int)$doneOrder['amount'];
    $feeAmount = $displayPrice - $price;
} elseif ($step === 1 && $uniqueFeeEnabled) {
    $feeAmount = $_SESSION['checkout_fee'] ?? 0;
    $displayPrice = $price + $feeAmount;
}
$displayPriceStr = 'Rp ' . number_format($displayPrice, 0, ',', '.');
$feeAmountStr = 'Rp ' . number_format($feeAmount, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Checkout — <?= htmlspecialchars($siteTitle) ?></title>
<?php if ($favicon): ?>
<link rel="icon" href="/assets/img/<?= htmlspecialchars($favicon) ?>?v=<?= time() ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="stylesheet" href="assets/css/checkout.css">
</head>
<body class="checkout-page">

<!-- Secure Checkout Bar -->
<div class="checkout-secure-bar">
  <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
  Checkout Aman &amp; Terenkripsi — Pembayaran diproses melalui QRIS resmi Bank Indonesia
</div>

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

<div class="checkout-wrapper">
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
    <div class="alert alert--error">⚠️ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <!-- ===== STEP 1: Masukkan Email ===== -->
    <?php if ($step === 1): ?>
    <div class="step-panel active" id="panel-1">
      <div class="checkout-email-icon">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
      </div>
      <div class="checkout-section-title">Informasi Aktivasi</div>
      <div class="checkout-section-sub">Ke mana link aktivasi Google AI Pro harus dikirim?</div>

      <form method="POST" id="form-checkout">
        <input type="hidden" name="action" value="create_order">

        <div id="email-section" style="animation:fadeUp .3s ease both">
          <div class="form-group">
            <label class="form-label" id="email-label">Email Tujuan Aktivasi</label>
            <input class="form-control" type="email" name="email" id="email-input"
                   placeholder="nama@gmail.com" required autocomplete="email">
            <div class="form-hint" id="email-hint">Link undangan resmi Google akan dikirim ke alamat email ini</div>
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
        <div class="qris-amount" id="qris-amount"><?= $displayPriceStr ?></div>
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
        Jika sudah bayar namun belum dikonfirmasi dalam 15 menit, hubungi admin via Threads dengan kode order dan bukti pembayaran Anda.
      </div>

      <div class="order-code-box">
        <div class="order-code-label">Kode Order Anda:</div>
        <div class="order-code-value"><?= htmlspecialchars($currentOrder['order_code']) ?></div>
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
        <div class="success-info">
          <div class="success-info__label">Email tujuan aktivasi:</div>
          <div class="success-info__value"><?= htmlspecialchars($doneOrder['email']) ?></div>
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
        <div class="pending-code-box">
          <div class="label">Kode Order:</div>
          <code><?= htmlspecialchars($doneOrder['order_code']) ?></code>
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
      <span class="order-line__label">Harga untuk 12 bulan</span>
      <span class="order-line__value"><?= $priceStr ?></span>
    </div>
    <?php if ($feeAmount > 0): ?>
    <div class="order-line">
      <span class="order-line__label">Fee Service</span>
      <span class="order-line__value" style="color:var(--c-blue)">+ <?= $feeAmountStr ?></span>
    </div>
    <?php endif; ?>
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
      <span class="order-total__price"><?= $displayPriceStr ?></span>
    </div>

    <div class="checkout-trust">
      <div class="checkout-trust-item">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2z"/></svg>
        Pembayaran 100% Aman &amp; Terenkripsi
      </div>
      <div class="checkout-trust-item">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        Link aktivasi asli langsung dari Google
      </div>
      <div class="checkout-trust-item">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
        Konfirmasi 1&ndash;5 Menit
      </div>
      <div class="checkout-trust-item">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        Semua E-Wallet QRIS didukung
      </div>
    </div>

    <!-- Guarantee badge -->
    <div class="guarantee-badge">
      <div class="guarantee-badge__icon">🛡️</div>
      <div><div class="guarantee-badge__title">Garansi Uang Kembali 7 Hari</div><div class="guarantee-badge__text">Jika ada masalah dengan aktivasi, kami refund 100% tanpa pertanyaan.</div></div>
    </div>

    <!-- Accepted wallets -->
    <div class="wallets-section">
      <div class="wallets-label">Diterima via QRIS</div>
      <div class="wallets-grid">
        <?php foreach (['GoPay','OVO','DANA','ShopeePay','LinkAja','BCA','BRI','BNI','Mandiri','BSI'] as $w): ?>
        <span class="wallet-pill"><?= $w ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div><!-- /checkout-summary -->

</div><!-- /checkout-layout -->
</div>

<!-- Toast element -->
<div id="toast-container"></div>

<script>
// ------- Step 1: Validation -------
document.getElementById('form-checkout')?.addEventListener('submit', function(e) {
  const emailInput = document.getElementById('email-input');
  if (!emailInput || !emailInput.value.trim()) {
    e.preventDefault();
    alert('Masukkan email Anda terlebih dahulu.');
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
      <span style="font-size:28px">⚠️ </span>
      <span style="font-size:12px">${data.msg || 'QRIS belum tersedia. Hubungi admin.'}</span>
    </div>`;
  }
}
loadQris();

// Toast notification
let toastTimer;
function showToast(msg, type = 'info') {
  const toast = document.getElementById('toast-container');
  if (!toast) return;
  toast.className = 'show toast-' + type;
  toast.innerHTML = (type === 'error' ? '⚠️ ' : (type === 'success' ? '✅ ' : 'ℹ️ ')) + msg;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.className = '', 3000);
}

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
      showToast('Pembayaran ditolak. Alasan: ' + (data.reason || 'Tidak ada keterangan'), 'error');
    } else {
      if (btn) { btn.disabled = false; btn.textContent = '🔄 Cek Status Pembayaran'; }
      showToast('Pembayaran belum dikonfirmasi. Tunggu sebentar.', 'info');
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
