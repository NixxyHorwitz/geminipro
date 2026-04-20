<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
csrf_enforce();

use App\Config;

$flash     = '';
$flashType = 'success';

// Favicon upload path
$faviconDir  = dirname(__DIR__) . '/assets/img/';
$faviconPath = $faviconDir . 'favicon.png';
$faviconUrl  = '/assets/img/favicon.png';

// Ensure dir exists
if (!is_dir($faviconDir)) mkdir($faviconDir, 0755, true);

// ── Save site identity ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_identity') {
        $title     = trim($_POST['site_title']   ?? 'Google AI Pro');
        $tagline   = trim($_POST['site_tagline'] ?? '');
        $footerTxt = trim($_POST['footer_text']  ?? '');

        Config::set($pdo, 'site_title',   $title);
        Config::set($pdo, 'site_tagline', $tagline);
        Config::set($pdo, 'footer_text',  $footerTxt);
        $flash = 'Identitas situs berhasil disimpan!';
    }

    if ($action === 'upload_favicon') {
        $file = $_FILES['favicon'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $flash = 'Upload gagal atau tidak ada file.'; $flashType = 'error';
        } else {
            $mime = mime_content_type($file['tmp_name']);
            $allowedMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/webp', 'image/svg+xml'];
            if (!in_array($mime, $allowedMimes)) {
                $flash = 'Format tidak didukung. Gunakan PNG, ICO, JPG, SVG, atau WebP.'; $flashType = 'error';
            } elseif ($file['size'] > 512 * 1024) {
                $flash = 'Ukuran file terlalu besar. Maksimal 512KB.'; $flashType = 'error';
            } else {
                // Save as PNG (or keep original extension)
                $ext = match($mime) {
                    'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
                    'image/svg+xml' => 'svg',
                    'image/gif'     => 'gif',
                    'image/webp'    => 'webp',
                    'image/jpeg'    => 'jpg',
                    default         => 'png',
                };
                $saveName = "favicon.{$ext}";
                // Remove old favicons
                foreach (glob($faviconDir . 'favicon.*') as $old) @unlink($old);
                move_uploaded_file($file['tmp_name'], $faviconDir . $saveName);
                Config::set($pdo, 'favicon_file', $saveName);
                $flash = "Favicon berhasil diupload! ({$saveName})";
            }
        }
    }

    if ($action === 'delete_favicon') {
        foreach (glob($faviconDir . 'favicon.*') as $old) @unlink($old);
        Config::set($pdo, 'favicon_file', '');
        $flash = 'Favicon berhasil dihapus.';
    }
}

// Reload
Config::loadFromDb($pdo);
$cfg = [
    'site_title'   => Config::get('site_title',   'Google AI Pro'),
    'site_tagline' => Config::get('site_tagline',  'Paket Lengkap 12 Bulan'),
    'footer_text'  => Config::get('footer_text',   ''),
    'favicon_file' => Config::get('favicon_file',  ''),
];
$hasFavicon = !empty($cfg['favicon_file']) && file_exists($faviconDir . $cfg['favicon_file']);

$pageTitle  = 'Site Identity';
$activePage = 'identity';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Site Identity</h1>
    <p class="page-sub">Upload favicon, atur judul & identitas website</p>
  </div>
</div>

<?php if ($flash): ?>
<div class="alert alert--<?= $flashType === 'error' ? 'error' : 'success' ?>" style="margin-bottom:20px">
  <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<div class="two-col-grid">

  <!-- Favicon upload -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Favicon Website</div>
      <?php if ($hasFavicon): ?>
      <span class="badge badge--success">Aktif</span>
      <?php else: ?>
      <span class="badge badge--neutral">Default</span>
      <?php endif; ?>
    </div>
    <div class="card__body">
      <!-- Current favicon preview -->
      <div style="text-align:center;margin-bottom:24px">
        <?php if ($hasFavicon): ?>
        <img src="/assets/img/<?= htmlspecialchars($cfg['favicon_file']) ?>?v=<?= time() ?>" 
             alt="Favicon" style="width:64px;height:64px;object-fit:contain;border:1px solid var(--c-border);border-radius:8px;padding:4px">
        <div style="font-size:12px;color:var(--c-text-hint);margin-top:8px"><?= htmlspecialchars($cfg['favicon_file']) ?></div>
        <?php else: ?>
        <div style="width:64px;height:64px;margin:0 auto;background:var(--c-bg-alt);border:2px dashed var(--c-border);border-radius:8px;display:flex;align-items:center;justify-content:center">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--c-text-hint)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </div>
        <div style="font-size:12px;color:var(--c-text-hint);margin-top:8px">Belum ada favicon</div>
        <?php endif; ?>
      </div>

      <!-- Upload form -->
      <form method="POST" enctype="multipart/form-data" id="favicon-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_favicon">
        <div class="form-group">
          <label class="form-label">Upload Favicon Baru</label>
          <div class="file-drop-zone" id="favicon-drop" onclick="document.getElementById('favicon-input').click()">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div id="drop-label">Klik atau drag & drop file</div>
            <div style="font-size:12px;color:var(--c-text-hint);margin-top:4px">PNG, ICO, SVG, JPG, WebP — maks 512KB</div>
          </div>
          <input type="file" id="favicon-input" name="favicon" accept="image/*,.ico" style="display:none" onchange="previewFavicon(this)">
        </div>
        <div style="display:flex;gap:10px">
          <button type="submit" class="btn btn--primary" style="flex:1">Upload Favicon</button>
          <?php if ($hasFavicon): ?>
          <form method="POST" style="display:contents" onsubmit="return confirm('Hapus favicon?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_favicon">
            <button type="submit" class="btn btn--danger">Hapus</button>
          </form>
          <?php endif; ?>
        </div>
      </form>

      <div class="alert alert--info" style="margin-top:16px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        <div>Ukuran favicon ideal: <strong>32×32px</strong> atau <strong>64×64px</strong>. Format .ICO paling kompatibel untuk semua browser.</div>
      </div>
    </div>
  </div>

  <!-- Site text identity -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Judul & Teks Situs</div>
    </div>
    <div class="card__body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_identity">
        <div class="form-group">
          <label class="form-label">Judul Situs (Site Title)</label>
          <input type="text" name="site_title" class="form-control" 
                 value="<?= htmlspecialchars($cfg['site_title']) ?>" 
                 placeholder="Google AI Pro" maxlength="80">
          <div class="form-hint">Digunakan di tab browser dan `&lt;title&gt;` semua halaman</div>
        </div>
        <div class="form-group">
          <label class="form-label">Tagline / Subtitle</label>
          <input type="text" name="site_tagline" class="form-control" 
                 value="<?= htmlspecialchars($cfg['site_tagline']) ?>" 
                 placeholder="Paket Lengkap 12 Bulan" maxlength="120">
          <div class="form-hint">Ditampilkan di hero section dan meta description</div>
        </div>
        <div class="form-group">
          <label class="form-label">Teks Footer</label>
          <input type="text" name="footer_text" class="form-control" 
                 value="<?= htmlspecialchars($cfg['footer_text']) ?>" 
                 placeholder="Bukan afiliasi resmi Google LLC." maxlength="200">
          <div class="form-hint">Teks disclaimer di bawah copyright footer</div>
        </div>
        <button type="submit" class="btn btn--primary btn--full">Simpan Identitas</button>
      </form>

      <div class="divider" style="margin:20px 0"></div>

      <div class="card__title" style="margin-bottom:12px">Preview</div>
      <div style="border:1px solid var(--c-border);border-radius:8px;overflow:hidden;font-size:13px">
        <!-- Browser tab mockup -->
        <div style="background:#e8eaed;padding:8px 12px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--c-border)">
          <div style="display:flex;gap:5px">
            <div style="width:10px;height:10px;border-radius:50%;background:#ea4335"></div>
            <div style="width:10px;height:10px;border-radius:50%;background:#fbbc04"></div>
            <div style="width:10px;height:10px;border-radius:50%;background:#34a853"></div>
          </div>
          <div style="background:#fff;border-radius:4px;padding:3px 12px;font-size:12px;display:flex;align-items:center;gap:6px;flex:1">
            <?php if ($hasFavicon): ?>
            <img src="/assets/img/<?= htmlspecialchars($cfg['favicon_file']) ?>" style="width:14px;height:14px;object-fit:contain">
            <?php else: ?>
            <div style="width:14px;height:14px;background:var(--c-blue);border-radius:2px"></div>
            <?php endif; ?>
            <span id="preview-title"><?= htmlspecialchars($cfg['site_title']) ?> — Paket Lengkap 12 Bulan</span>
          </div>
        </div>
        <div style="padding:12px 16px;background:#f8f9fa">
          <div style="font-size:22px;font-weight:700;color:#202124;margin-bottom:4px" id="preview-heading"><?= htmlspecialchars($cfg['site_title']) ?></div>
          <div style="color:#5f6368;font-size:13px" id="preview-tagline"><?= htmlspecialchars($cfg['site_tagline']) ?></div>
          <div style="margin-top:12px;font-size:11px;color:#9aa0a6">
            © <?= date('Y') ?> <span id="preview-footer"><?= htmlspecialchars($cfg['site_title']) ?> Reseller. <?= htmlspecialchars($cfg['footer_text']) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.file-drop-zone {
  border: 2px dashed var(--c-border);
  border-radius: 8px;
  padding: 28px 20px;
  text-align: center;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  color: var(--c-text-sec);
}
.file-drop-zone:hover, .file-drop-zone.dragover {
  border-color: var(--c-blue);
  background: var(--c-blue-light);
}
.file-drop-zone svg { margin: 0 auto 8px; display: block; opacity: .4; }
#favicon-preview { width:48px;height:48px;object-fit:contain;border-radius:6px;border:1px solid var(--c-border); }
</style>

<script>
// Live preview update
function updatePreview() {
  const title   = document.querySelector('[name=site_title]')?.value || 'Google AI Pro';
  const tagline = document.querySelector('[name=site_tagline]')?.value || '';
  const footer  = document.querySelector('[name=footer_text]')?.value || '';
  const pt = document.getElementById('preview-title');
  const ph = document.getElementById('preview-heading');
  const ptg = document.getElementById('preview-tagline');
  const pf = document.getElementById('preview-footer');
  if (pt)  pt.textContent  = title + (tagline ? ' — ' + tagline : '');
  if (ph)  ph.textContent  = title;
  if (ptg) ptg.textContent = tagline;
  if (pf)  pf.textContent  = title + ' Reseller. ' + footer;
}
document.querySelectorAll('[name=site_title],[name=site_tagline],[name=footer_text]')
  .forEach(el => el.addEventListener('input', updatePreview));

// Favicon preview
function previewFavicon(input) {
  const file = input.files[0];
  if (!file) return;
  const label = document.getElementById('drop-label');
  label.textContent = file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
}

// Drag & drop
const dropZone = document.getElementById('favicon-drop');
['dragenter','dragover'].forEach(ev => dropZone.addEventListener(ev, e => {
  e.preventDefault(); dropZone.classList.add('dragover');
}));
['dragleave','drop'].forEach(ev => dropZone.addEventListener(ev, e => {
  e.preventDefault(); dropZone.classList.remove('dragover');
}));
dropZone.addEventListener('drop', e => {
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('favicon-input').files = dt.files;
    previewFavicon(document.getElementById('favicon-input'));
  }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
