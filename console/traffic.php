<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;
$filter  = $_GET['action'] ?? 'all';

try {
    $where    = $filter !== 'all' ? "WHERE action = " . $pdo->quote($filter) : '';
    $total    = (int) $pdo->query("SELECT COUNT(*) FROM traffic_logs {$where}")->fetchColumn();
    $logs     = $pdo->query("SELECT * FROM traffic_logs {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
    $actions  = $pdo->query("SELECT action, COUNT(*) as cnt FROM traffic_logs GROUP BY action ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);

    // Today summary
    $todayTotal   = (int) $pdo->query("SELECT COUNT(*) FROM traffic_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $uniqueIps    = (int) $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM traffic_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $checkouts    = (int) $pdo->query("SELECT COUNT(*) FROM traffic_logs WHERE action='order_created' AND DATE(created_at)=CURDATE()")->fetchColumn();
    $convRate     = $todayTotal > 0 ? round(($checkouts / $todayTotal) * 100, 1) : 0;

    // Hourly chart (today)
    $hourlyData = $pdo->query(
        "SELECT HOUR(created_at) as h, COUNT(*) as cnt FROM traffic_logs 
         WHERE DATE(created_at)=CURDATE() GROUP BY HOUR(created_at) ORDER BY h"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch(\Exception $e) {
    $logs = $actions = $hourlyData = [];
    $total = $todayTotal = $uniqueIps = $checkouts = $convRate = 0;
}

$totalPages = max(1, (int) ceil($total / $perPage));
$pageTitle  = 'Traffic & Stats';
$activePage = 'traffic';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Traffic & Statistik</h1>
  <p class="page-sub">Pantau semua aktivitas pengunjung secara real-time</p>
</div>

<!-- Today overview -->
<div class="stat-grid">
  <div class="stat-card stat-card--blue">
    <div class="stat-card__icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
    <div class="stat-card__body">
      <div class="stat-card__label">Hits Hari Ini</div>
      <div class="stat-card__value"><?= number_format($todayTotal) ?></div>
    </div>
  </div>
  <div class="stat-card stat-card--green">
    <div class="stat-card__icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/></svg></div>
    <div class="stat-card__body">
      <div class="stat-card__label">IP Unik Hari Ini</div>
      <div class="stat-card__value"><?= number_format($uniqueIps) ?></div>
    </div>
  </div>
  <div class="stat-card stat-card--yellow">
    <div class="stat-card__icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg></div>
    <div class="stat-card__body">
      <div class="stat-card__label">Order Hari Ini</div>
      <div class="stat-card__value"><?= $checkouts ?></div>
    </div>
  </div>
  <div class="stat-card stat-card--purple">
    <div class="stat-card__icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
    <div class="stat-card__body">
      <div class="stat-card__label">Conversion Rate</div>
      <div class="stat-card__value"><?= $convRate ?>%</div>
    </div>
  </div>
</div>

<!-- Hourly chart -->
<div class="card" style="margin-top:16px">
  <div class="card__header"><div class="card__title">Aktivitas Per Jam (Hari Ini)</div></div>
  <div class="card__body"><canvas id="hourlyChart" height="120"></canvas></div>
</div>

<!-- Action filter + logs -->
<div class="card" style="margin-top:16px">
  <div class="card__header">
    <div class="card__title">Log Aktivitas</div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a href="?action=all" class="btn btn--xs <?= $filter==='all' ?'btn--primary':'btn--ghost' ?>">Semua</a>
      <?php foreach ($actions as $ac): ?>
      <a href="?action=<?= urlencode($ac['action'] ?? '') ?>" class="btn btn--xs <?= $filter===$ac['action']?'btn--primary':'btn--ghost' ?>">
        <?= htmlspecialchars($ac['action'] ?? 'null') ?> <span style="opacity:.6">(<?= $ac['cnt'] ?>)</span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card__body" style="padding:0">
    <table class="table table--compact">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Halaman</th>
          <th>Aksi</th>
          <th>IP Address</th>
          <th>Data</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td style="white-space:nowrap;font-size:12px;color:var(--c-text-hint)"><?= date('d/m H:i:s', strtotime($log['created_at'])) ?></td>
          <td><code style="font-size:12px"><?= htmlspecialchars($log['page']) ?></code></td>
          <td><span class="badge badge--neutral" style="font-size:11px"><?= htmlspecialchars($log['action'] ?? '-') ?></span></td>
          <td style="font-size:12px;font-family:monospace"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
          <td style="font-size:11px;color:var(--c-text-hint);max-width:200px;overflow:hidden;text-overflow:ellipsis">
            <?= $log['data'] ? htmlspecialchars(substr($log['data'], 0, 100)) : '-' ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--c-text-hint)">Belum ada log</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
  <a href="?action=<?= urlencode($filter) ?>&page=<?= $p ?>" class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const hourlyData = <?= json_encode($hourlyData) ?>;
const hourLabels = Array.from({length:24}, (_,i) => i+':00');
const hourArr = Array(24).fill(0);
hourlyData.forEach(r => { hourArr[parseInt(r.h)] = parseInt(r.cnt); });

new Chart(document.getElementById('hourlyChart'), {
  type: 'line',
  data: {
    labels: hourLabels,
    datasets: [{
      label: 'Hits',
      data: hourArr,
      borderColor: '#4285F4',
      backgroundColor: 'rgba(66,133,244,0.08)',
      fill: true,
      tension: 0.4,
      pointRadius: 3,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
