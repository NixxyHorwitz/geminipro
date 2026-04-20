<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
csrf_enforce();

use App\Config;

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_product') {
        $price = (int) preg_replace('/\D/', '', $_POST['product_price'] ?? '0');
        $name  = htmlspecialchars(trim($_POST['product_name'] ?? 'Google AI Pro'));
        $dur   = (int) ($_POST['product_duration'] ?? 12);
        $timeout = (int) ($_POST['payment_timeout'] ?? 15);

        if ($price < 1000) {
            $flash = 'Harga minimal Rp 1.000';
            $flashType = 'error';
        } else {
            Config::set($pdo, 'product_price', (string) $price);
            Config::set($pdo, 'product_name', $name);
            Config::set($pdo, 'product_duration', (string) $dur);
            Config::set($pdo, 'payment_timeout_minutes', (string) $timeout);
            $flash = 'Pengaturan produk berhasil disimpan!';
        }
    }

    if ($action === 'save_site') {
        $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');
        if (empty($siteUrl)) {
            $flash = 'URL site tidak boleh kosong.';
            $flashType = 'error';
        } else {
            Config::set($pdo, 'site_url', $siteUrl);
            Config::writeEnv(['APP_URL' => $siteUrl]);
            $flash = 'URL Site berhasil disimpan!';
        }
    }

    if ($action === 'clear_logs') {
        $days = (int) ($_POST['days'] ?? 30);
        $pdo->exec("DELETE FROM traffic_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)");
        $cnt = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
        $flash = "Log traffic lebih dari {$days} hari yang lalu berhasil dihapus.";
    }
}

// Reload config
Config::loadFromDb($pdo);

$settings = [
    'product_price'            => Config::get('product_price', '309000'),
    'product_name'             => Config::get('product_name', 'Google AI Pro'),
    'product_duration'         => Config::get('product_duration', '12'),
    'payment_timeout_minutes'  => Config::get('payment_timeout_minutes', '15'),
    'site_url'                 => Config::get('site_url', ''),
];

$pageTitle  = 'Settings';
$activePage = 'settings';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Pengaturan</h1>
  <p class="page-sub">Konfigurasi produk dan website</p>
</div>

<?php if ($flash): ?>
<div class="alert alert--<?= $flashType === 'error' ? 'error' : 'success' ?>" style="margin-bottom:20px">
  <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<div class="two-col-grid">
  <!-- Product settings -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Pengaturan Produk</div>
    </div>
    <div class="card__body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_product">
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
          <div class="form-hint">Order otomatis expired setelah tidak punya bayaran dalam waktu ini</div>
        </div>
        <button type="submit" class="btn btn--primary">Simpan Produk</button>
      </form>
    </div>
  </div>

  <!-- Site settings -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Pengaturan Website</div>
    </div>
    <div class="card__body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_site">
        <div class="form-group">
          <label class="form-label">URL Website (tanpa trailing slash)</label>
          <input type="url" name="site_url" class="form-control" value="<?= htmlspecialchars($settings['site_url']) ?>" placeholder="https://yourdomain.com">
          <div class="form-hint">URL publik website tempat checkout berjalan</div>
        </div>
        <button type="submit" class="btn btn--primary">Simpan URL</button>
      </form>

      <div class="divider" style="margin:24px 0"></div>

      <!-- Log management -->
      <div class="card__title" style="margin-bottom:12px">Manajemen Log</div>
      <form method="POST" onsubmit="return confirm('Hapus log traffic lama?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="clear_logs">
        <div style="display:flex;align-items:center;gap:12px">
          <span style="font-size:14px;white-space:nowrap">Hapus log lebih dari</span>
          <input type="number" name="days" class="form-control" value="30" min="1" max="365" style="width:80px">
          <span style="font-size:14px;white-space:nowrap">hari</span>
        </div>
        <button type="submit" class="btn btn--danger btn--sm" style="margin-top:12px">Hapus Log Lama</button>
      </form>
    </div>
  </div>
</div>

<!-- DB Info -->
<div class="card" style="margin-top:16px">
  <div class="card__header"><div class="card__title">Informasi Sistem</div></div>
  <div class="card__body">
    <div class="info-grid">
      <div class="info-row"><span>PHP Version</span><strong><?= PHP_VERSION ?></strong></div>
      <div class="info-row"><span>Database</span><strong><?= Config::env('DB_DATABASE') ?>@<?= Config::env('DB_HOST') ?></strong></div>
      <div class="info-row"><span>Server Time</span><strong><?= date('d M Y H:i:s') ?></strong></div>
      <?php
      try {
        $tlSize = (int) $pdo->query("SELECT COUNT(*) FROM traffic_logs")->fetchColumn();
        echo "<div class='info-row'><span>Traffic Logs</span><strong>" . number_format($tlSize) . " baris</strong></div>";
      } catch(\Exception $e) {}
      ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
