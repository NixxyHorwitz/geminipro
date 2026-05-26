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

    if ($action === 'save_site') {
        $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $loadingScreen = isset($_POST['landing_loading_screen']) ? '1' : '0';

        if (empty($siteUrl)) {
            $flash = 'URL site tidak boleh kosong.';
            $flashType = 'error';
        } else {
            Config::set($pdo, 'site_url', $siteUrl);
            Config::set($pdo, 'landing_loading_screen', $loadingScreen);
            Config::writeEnv(['APP_URL' => $siteUrl]);
            $flash = 'Pengaturan Website berhasil disimpan!';
        }
    }

    if ($action === 'save_contact') {
        $tg = trim($_POST['telegram_contact'] ?? '');
        Config::set($pdo, 'telegram_contact', $tg);
        $flash = 'Kontak Telegram berhasil disimpan!';
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
    'site_url'               => Config::get('site_url', ''),
    'telegram_contact'       => Config::get('telegram_contact', ''),
    'landing_loading_screen' => Config::get('landing_loading_screen', '1'),
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
        
        <div class="form-group" style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border)">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
            <input type="checkbox" name="landing_loading_screen" value="1" <?= $settings['landing_loading_screen'] == '1' ? 'checked' : '' ?>>
            <strong>Gunakan Animasi Loading di Domain Utama</strong>
          </label>
          <div class="form-hint" style="margin-left:26px">
            Jika dicentang, pengunjung di index (/) akan melihat loading screen 1.5 detik lalu dialihkan ke /plan (menghindari deteksi bot URL shortener s.id). Jika tidak dicentang, akan langsung dialihkan (Direct PHP Redirect).
          </div>
        </div>

        <button type="submit" class="btn btn--primary" style="margin-top:12px">Simpan Pengaturan</button>
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

  <!-- Contact settings -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Kontak &amp; Support</div>
    </div>
    <div class="card__body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_contact">
        <div class="form-group">
          <label class="form-label">Username Telegram Admin</label>
          <input type="text" name="telegram_contact" class="form-control"
                 value="<?= htmlspecialchars($settings['telegram_contact']) ?>"
                 placeholder="@username atau username">
          <div class="form-hint">Ditampilkan di footer website sebagai tombol hubungi admin. Kosongkan untuk menyembunyikan.</div>
        </div>
        <button type="submit" class="btn btn--primary">Simpan Kontak</button>
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
