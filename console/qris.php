<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
csrf_enforce();

use App\QrisHelper;
use App\Config;

$flash = '';
$flashType = 'success';
$qrisInfo  = null;

// Load active QRIS
try {
    $active = $pdo->query("SELECT * FROM qris_templates WHERE active=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch(\Exception $e) { $active = null; }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_raw') {
        $raw = trim($_POST['raw_string'] ?? '');
        if (!$raw) {
            $flash = 'Raw string QRIS tidak boleh kosong.';
            $flashType = 'error';
        } elseif (!QrisHelper::validate($raw) && strlen($raw) < 20) {
            $flash = 'String QRIS tidak valid. Pastikan formatnya benar.';
            $flashType = 'error';
        } else {
            $merchant = QrisHelper::getMerchantName($raw);
            // Deactivate all existing
            $pdo->exec("UPDATE qris_templates SET active=0");
            $stmt = $pdo->prepare("INSERT INTO qris_templates (raw_string, merchant_name, active) VALUES (?,?,1)");
            $stmt->execute([$raw, $merchant]);
            $active = $pdo->query("SELECT * FROM qris_templates WHERE active=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $flash = "QRIS berhasil disimpan! Merchant: {$merchant}";
        }
    }

    if ($action === 'save_product') {
        $price = (int) preg_replace('/\D/', '', $_POST['product_price'] ?? '0');
        $name  = htmlspecialchars(trim($_POST['product_name'] ?? 'Google AI Pro'));
        $dur   = (int) ($_POST['product_duration'] ?? 12);
        $timeout = (int) ($_POST['payment_timeout'] ?? 15);
        $uniqueEnabled = isset($_POST['unique_fee_enabled']) ? '1' : '0';
        $uniqueMin = (int) ($_POST['unique_fee_min'] ?? 1);
        $uniqueMax = (int) ($_POST['unique_fee_max'] ?? 999);

        if ($price < 1000) {
            $flash = 'Harga minimal Rp 1.000';
            $flashType = 'error';
        } else {
            Config::set($pdo, 'product_price', (string) $price);
            Config::set($pdo, 'product_name', $name);
            Config::set($pdo, 'product_duration', (string) $dur);
            Config::set($pdo, 'payment_timeout_minutes', (string) $timeout);
            Config::set($pdo, 'unique_fee_enabled', $uniqueEnabled);
            Config::set($pdo, 'unique_fee_min', (string) $uniqueMin);
            Config::set($pdo, 'unique_fee_max', (string) $uniqueMax);
            $flash = 'Pengaturan produk dan biaya layanan berhasil disimpan!';
        }
    }

    if ($action === 'test_qris' && $active) {
        $amount = (int) preg_replace('/\D/', '', $_POST['test_amount'] ?? '10000');
        $dynamic = QrisHelper::setAmount($active['raw_string'], $amount);
        $img = QrisHelper::generateQrImage($dynamic, 200);
        $qrisInfo = ['img' => $img, 'dynamic' => $dynamic, 'amount' => $amount];
    }
}

Config::loadFromDb($pdo);
$settings = [
    'product_price'            => Config::get('product_price', '309000'),
    'product_name'             => Config::get('product_name', 'Google AI Pro'),
    'product_duration'         => Config::get('product_duration', '12'),
    'payment_timeout_minutes'  => Config::get('payment_timeout_minutes', '15'),
    'unique_fee_enabled'       => Config::get('unique_fee_enabled', '0'),
    'unique_fee_min'           => Config::get('unique_fee_min', '1'),
    'unique_fee_max'           => Config::get('unique_fee_max', '999'),
];

$pageTitle  = 'Payment & Method';
$activePage = 'qris';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Payment & Method</h1>
  <p class="page-sub">Konfigurasi produk, QRIS, dan biaya layanan (Unique Fee)</p>
</div>

<?php if ($flash): ?>
<div class="alert alert--<?= $flashType === 'error' ? 'error' : 'success' ?>" style="margin-bottom:20px">
  <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<div class="two-col-grid">

  <!-- Active QRIS -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">QRIS Aktif</div>
      <?php if ($active): ?>
      <span class="badge badge--success">Aktif</span>
      <?php else: ?>
      <span class="badge badge--error">Belum dikonfigurasi</span>
      <?php endif; ?>
    </div>
    <div class="card__body">
      <?php if ($active): ?>
        <div class="info-row"><span>Merchant</span><strong><?= htmlspecialchars($active['merchant_name'] ?? '-') ?></strong></div>
        <div class="info-row"><span>Ditambahkan</span><strong><?= date('d M Y H:i', strtotime($active['created_at'])) ?></strong></div>
        <div class="info-row"><span>Raw String (50 char)</span><code style="font-size:11px;word-break:break-all"><?= htmlspecialchars(substr($active['raw_string'], 0, 50)) ?>...</code></div>
        
        <form method="POST" style="margin-top:20px;display:flex;gap:8px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="test_qris">
          <input type="number" name="test_amount" class="form-control" placeholder="Nominal" required style="width:120px">
          <button type="submit" class="btn btn--outline" style="flex:1">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Test Generate
          </button>
        </form>

        <?php if ($qrisInfo): ?>
        <div style="text-align:center;margin-top:20px;padding:16px;background:var(--c-bg-alt);border-radius:8px">
          <?php if ($qrisInfo['img']): ?>
          <img src="<?= $qrisInfo['img'] ?>" alt="QR Test" style="max-width:180px;border-radius:6px">
          <?php else: ?>
          <div style="color:var(--c-text-hint);font-size:13px">Gagal generate gambar (cek koneksi internet)</div>
          <?php endif; ?>
          <div style="font-size:12px;color:var(--c-text-sec);margin-top:8px">Preview — Nominal: <?= \App\Order::formatRp($qrisInfo['amount']) ?></div>
        </div>
        <?php endif; ?>

      <?php else: ?>
        <div style="text-align:center;padding:32px 0;color:var(--c-text-hint)">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;opacity:.4"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 00-2 2v3"/></svg>
          Belum ada QRIS yang dikonfigurasi.<br>
          <span style="font-size:13px">Paste raw string QRIS di sebelah kanan.</span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Set QRIS form -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Input Raw String QRIS</div>
    </div>
    <div class="card__body">
      <div class="alert alert--info" style="margin-bottom:16px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        Dapatkan raw string QRIS dari bank/e-wallet Anda. Biasanya berformat string panjang dimulai dengan <code>000201</code>.
      </div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_raw">
        <div class="form-group">
          <label class="form-label">Raw String QRIS</label>
          <textarea name="raw_string" id="raw_string" class="form-control" rows="6"
            placeholder="000201010211261800141..."
            style="font-family:monospace;font-size:12px;letter-spacing:.02em"><?= $active ? htmlspecialchars($active['raw_string']) : '' ?></textarea>
          <div class="form-hint">Paste string QRIS mentah. Sistem akan menghitung ulang CRC secara otomatis saat generate QR dinamis.</div>
        </div>
        <button type="submit" class="btn btn--primary btn--full">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Simpan QRIS
        </button>
      </form>
    </div>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card__header">
    <div class="card__title">Pengaturan Produk & Biaya Layanan</div>
  </div>
  <div class="card__body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_product">
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
        <div class="form-group">
          <label class="form-label">Nama Produk</label>
          <input type="text" name="product_name" class="form-control" value="<?= htmlspecialchars($settings['product_name']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Harga (IDR)</label>
          <div class="input-prefix-wrap">
            <span class="input-prefix">Rp</span>
            <input type="number" name="product_price" class="form-control" value="<?= $settings['product_price'] ?>" min="1000">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Durasi (bulan)</label>
          <input type="number" name="product_duration" class="form-control" value="<?= $settings['product_duration'] ?>" min="1" max="60">
        </div>
        <div class="form-group">
          <label class="form-label">Batas Waktu Pembayaran (menit)</label>
          <input type="number" name="payment_timeout" class="form-control" value="<?= $settings['payment_timeout_minutes'] ?>" min="5" max="60">
        </div>
      </div>
      
      <div class="divider" style="margin:24px 0"></div>
      
      <div class="card__title" style="margin-bottom:12px;font-size:15px">Sistem Kode Unik (Fee Service)</div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:12px">
          <input type="checkbox" name="unique_fee_enabled" value="1" <?= $settings['unique_fee_enabled'] === '1' ? 'checked' : '' ?> style="width:16px;height:16px;cursor:pointer">
          <span style="font-weight:600">Aktifkan Kode Unik Pembayaran</span>
        </label>
        <div class="form-hint">Sistem akan menambahkan angka unik secara acak di 3 digit terakhir nominal (contoh: Rp 309.123). Berguna untuk membedakan transaksi dan menambah pendapatan ekstra (fee).</div>
      </div>
      
      <div style="display:flex;gap:16px;align-items:center;margin-top:16px">
        <div class="form-group" style="flex:1;margin:0">
          <label class="form-label">Range Unik Minimum</label>
          <input type="number" name="unique_fee_min" class="form-control" value="<?= $settings['unique_fee_min'] ?>" min="1" max="998">
        </div>
        <div style="padding-top:24px;font-weight:bold;color:var(--c-text-hint)">—</div>
        <div class="form-group" style="flex:1;margin:0">
          <label class="form-label">Range Unik Maksimum</label>
          <input type="number" name="unique_fee_max" class="form-control" value="<?= $settings['unique_fee_max'] ?>" min="2" max="999">
        </div>
      </div>
      
      <button type="submit" class="btn btn--primary" style="margin-top:24px">Simpan Pengaturan Produk</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card__header">
    <div class="card__title">Panduan QRIS</div>
  </div>
  <div class="card__body">
    <div class="guide-steps">
      <div class="guide-step"><div class="guide-step__num">1</div><div><strong>Buka aplikasi mobile banking atau e-wallet Anda</strong><br><span style="font-size:13px;color:var(--c-text-sec)">GoPay, OVO, Dana, BCA Mobile, dll yang support QRIS statis</span></div></div>
      <div class="guide-step"><div class="guide-step__num">2</div><div><strong>Buka fitur "Terima Pembayaran" / "Receive Money"</strong><br><span style="font-size:13px;color:var(--c-text-sec)">Pilih QRIS statis, lalu tampilkan kode QR Anda</span></div></div>
      <div class="guide-step"><div class="guide-step__num">3</div><div><strong>Scan QR menggunakan scanner seperti ZXing atau WeChat</strong><br><span style="font-size:13px;color:var(--c-text-sec)">Hasil scan adalah raw string panjang yang dimulai dengan <code>000201</code></span></div></div>
      <div class="guide-step"><div class="guide-step__num">4</div><div><strong>Paste raw string di atas dan simpan</strong><br><span style="font-size:13px;color:var(--c-text-sec)">Sistem akan otomatis memperbarui nominal sesuai harga produk</span></div></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
