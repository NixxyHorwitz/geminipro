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

    if ($action === 'test_qris' && $active) {
        $amount = (int) Config::get('product_price', 309000);
        $dynamic = QrisHelper::setAmount($active['raw_string'], $amount);
        $img = QrisHelper::generateQrImage($dynamic, 200);
        $qrisInfo = ['img' => $img, 'dynamic' => $dynamic, 'amount' => $amount];
    }
}

$pageTitle  = 'Set QRIS';
$activePage = 'qris';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Konfigurasi QRIS</h1>
  <p class="page-sub">Upload atau paste raw string QRIS untuk pembayaran dinamis</p>
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
        
        <form method="POST" style="margin-top:20px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="test_qris">
          <button type="submit" class="btn btn--outline btn--full">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Test Generate QR Code
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
