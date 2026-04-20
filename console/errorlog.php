<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
csrf_enforce();

$logFile = dirname(__DIR__) . '/error_log.txt';
$flash   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clear_log') {
        file_put_contents($logFile, '');
        $flash = 'Error log berhasil dibersihkan.';
    }
}

// Read log file — last N lines
$lines    = [];
$maxLines = (int) ($_GET['lines'] ?? 200);
if (file_exists($logFile) && filesize($logFile) > 0) {
    $content = file_get_contents($logFile);
    $lines   = array_filter(array_reverse(explode("\n", trim($content))));
    $total   = count($lines);
    $lines   = array_slice($lines, 0, $maxLines);
} else {
    $total = 0;
}

$fileSize = file_exists($logFile) ? filesize($logFile) : 0;

// Filter
$filter = trim($_GET['q'] ?? '');
if ($filter) {
    $lines = array_filter($lines, fn($l) => stripos($l, $filter) !== false);
}

$pageTitle  = 'Error Log';
$activePage = 'errorlog';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Error Log Viewer</h1>
    <p class="page-sub">File: <code>error_log.txt</code> — 
      <?= $total ?> baris total — 
      <?= $fileSize > 0 ? number_format($fileSize / 1024, 1) . ' KB' : '0 KB' ?>
    </p>
  </div>
  <div class="page-header__actions">
    <form method="POST" onsubmit="return confirm('Hapus semua error log?')">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear_log">
      <button type="submit" class="btn btn--danger btn--sm">Hapus Log</button>
    </form>
    <a href="?lines=<?= $maxLines ?>" class="btn btn--ghost btn--sm">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 11-.92-10.26l5.08-5.08"/></svg>
      Refresh
    </a>
  </div>
</div>

<?php if ($flash): ?>
<div class="alert alert--success" style="margin-bottom:16px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<!-- Controls -->
<div class="card" style="margin-bottom:16px">
  <div class="card__body" style="padding:14px 20px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <div class="input-wrap" style="flex:1;min-width:200px">
        <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <form method="GET" style="flex:1;display:contents">
          <input type="text" name="q" class="form-control" placeholder="Filter teks error..." value="<?= htmlspecialchars($filter) ?>" style="padding-left:34px">
        </form>
      </div>
      <div style="display:flex;gap:6px;align-items:center">
        <span style="font-size:13px;color:var(--c-text-sec)">Tampilkan:</span>
        <?php foreach ([100, 200, 500, 1000] as $n): ?>
        <a href="?lines=<?= $n ?>&q=<?= urlencode($filter) ?>" 
           class="btn btn--xs <?= $maxLines === $n ? 'btn--primary' : 'btn--ghost' ?>"><?= $n ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Log content -->
<div class="card">
  <div class="card__header">
    <div class="card__title">
      Log Terbaru (<?= count($lines) ?><?= $filter ? " hasil filter '{$filter}'" : " baris" ?>)
    </div>
    <div style="display:flex;gap:6px">
      <button onclick="toggleWrap()" class="btn btn--ghost btn--xs" id="wrap-btn">Wrap: OFF</button>
      <button onclick="document.getElementById('log-box').scrollTop=0" class="btn btn--ghost btn--xs">↑ Atas</button>
    </div>
  </div>
  <div class="card__body" style="padding:0">
    <?php if (empty($lines)): ?>
      <div style="padding:40px;text-align:center;color:var(--c-text-hint)">
        <?= $fileSize === 0 ? '✅ Tidak ada error — log kosong' : '0 hasil ditemukan untuk filter ini' ?>
      </div>
    <?php else: ?>
    <div id="log-box" style="max-height:70vh;overflow-y:auto;overflow-x:auto;padding:0;background:#1a1f2e;border-radius:0 0 10px 10px">
      <table style="width:100%;border-collapse:collapse;font-family:monospace;font-size:12px">
        <tbody>
        <?php
        $lineNum = 0;
        foreach ($lines as $line):
          $lineNum++;
          $line = htmlspecialchars($line);
          // Color-code by severity
          if (stripos($line, 'fatal') !== false || stripos($line, 'FATAL') !== false) {
              $color = '#ff6b6b'; $bg = 'rgba(255,107,107,0.08)';
          } elseif (stripos($line, 'error') !== false || stripos($line, 'exception') !== false) {
              $color = '#ffa07a'; $bg = 'rgba(255,160,122,0.06)';
          } elseif (stripos($line, 'warning') !== false) {
              $color = '#ffd700'; $bg = 'rgba(255,215,0,0.05)';
          } else {
              $color = '#8ab4f8'; $bg = 'transparent';
          }
          // Highlight filter
          if ($filter) {
              $line = str_ireplace(
                  htmlspecialchars($filter),
                  '<mark style="background:#fbbc04;color:#000">' . htmlspecialchars($filter) . '</mark>',
                  $line
              );
          }
        ?>
        <tr style="background:<?= $bg ?>">
          <td style="color:#4a5568;padding:3px 10px 3px 16px;user-select:none;text-align:right;border-right:1px solid #2d3748;width:40px"><?= $lineNum ?></td>
          <td style="color:<?= $color ?>;padding:3px 16px;white-space:nowrap"><?= $line ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Auto-refresh toggle -->
<div style="margin-top:12px;display:flex;align-items:center;gap:12px">
  <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--c-text-sec);cursor:pointer">
    <input type="checkbox" id="auto-refresh" style="cursor:pointer">
    Auto-refresh setiap 10 detik
  </label>
  <?php if ($total > $maxLines): ?>
  <span style="font-size:12px;color:var(--c-text-hint)">Menampilkan <?= $maxLines ?> dari <?= $total ?> baris. 
    <a href="?lines=<?= $total ?>&q=<?= urlencode($filter) ?>">Lihat semua</a>
  </span>
  <?php endif; ?>
</div>

<script>
function toggleWrap() {
  const box = document.getElementById('log-box');
  const btn = document.getElementById('wrap-btn');
  const cells = box.querySelectorAll('td:last-child');
  const isWrapped = cells[0]?.style.whiteSpace === 'pre-wrap';
  cells.forEach(c => c.style.whiteSpace = isWrapped ? 'nowrap' : 'pre-wrap');
  btn.textContent = 'Wrap: ' + (isWrapped ? 'OFF' : 'ON');
}

// Filter on Enter
document.querySelector('[name=q]')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') e.target.closest('form')?.submit();
});

// Auto-refresh
let autoRefTimer;
document.getElementById('auto-refresh').addEventListener('change', function() {
  if (this.checked) {
    autoRefTimer = setInterval(() => location.reload(), 10000);
  } else {
    clearInterval(autoRefTimer);
  }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
