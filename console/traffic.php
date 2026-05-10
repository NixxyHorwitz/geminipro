<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

// ── Range selector ──────────────────────────────────────────────────────────
$range   = $_GET['range'] ?? 'day';          // jam|day|week|month|6month
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;
$offset  = ($page - 1) * $perPage;
$filter  = $_GET['action'] ?? 'all';

$validRanges = ['jam','day','week','month','6month'];
if (!in_array($range, $validRanges, true)) $range = 'day';

// ── Date helpers ─────────────────────────────────────────────────────────────
function rangeSQL(string $range): array {
    return match($range) {
        'jam'    => ['WHERE created_at >= NOW() - INTERVAL 24 HOUR',    'DATE_FORMAT(created_at,\'%H:00\')', 'HOUR(created_at)', 24, 'Jam'],
        'day'    => ['WHERE DATE(created_at)=CURDATE()',                 'HOUR(created_at)',                   'HOUR(created_at)', 24, 'Hari Ini'],
        'week'   => ['WHERE created_at >= CURDATE() - INTERVAL 6 DAY',  'DATE(created_at)',                   'DATE(created_at)', 7,  'Minggu Ini'],
        'month'  => ['WHERE created_at >= CURDATE() - INTERVAL 29 DAY', 'DATE(created_at)',                   'DATE(created_at)', 30, 'Bulan Ini'],
        '6month' => ['WHERE created_at >= CURDATE() - INTERVAL 180 DAY','DATE_FORMAT(created_at,\'%Y-%m\')', 'DATE_FORMAT(created_at,\'%Y-%m\')', 6, '6 Bulan'],
        default  => ['WHERE DATE(created_at)=CURDATE()', 'HOUR(created_at)', 'HOUR(created_at)', 24, 'Hari Ini'],
    };
}

try {
    [$rwhere, $rlabel, $rgroup, $rcount, $rtitle] = rangeSQL($range);

    // ── Summary stats for selected range ─────────────────────────────────────
    $totalHits   = (int) $pdo->query("SELECT COUNT(*) FROM traffic_logs {$rwhere}")->fetchColumn();
    $uniqueIps   = (int) $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM traffic_logs {$rwhere}")->fetchColumn();
    $orderHits   = (int) $pdo->query("SELECT COUNT(*) FROM traffic_logs {$rwhere} AND action='order_created'")->fetchColumn();
    $convRate    = $totalHits > 0 ? round(($orderHits / $totalHits) * 100, 2) : 0;

    // Orders confirmed from orders table in same range
    $rwhere2 = str_replace('created_at', 'o.created_at', $rwhere);
    $totalOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders o {$rwhere2}")->fetchColumn();
    $totalRevenue = (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders o {$rwhere2} AND o.status='confirmed'")->fetchColumn();
    $pendingOrders= (int) $pdo->query("SELECT COUNT(*) FROM orders o {$rwhere2} AND o.status='pending'")->fetchColumn();

    // ── Chart data: traffic hits ──────────────────────────────────────────────
    $trafficChart = $pdo->query(
        "SELECT {$rgroup} as lbl, COUNT(*) as cnt 
         FROM traffic_logs {$rwhere} 
         GROUP BY {$rgroup} ORDER BY {$rgroup}"
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── Chart data: orders ────────────────────────────────────────────────────
    $rwhere_orders = str_replace('created_at', 'created_at', $rwhere);
    $ordersChart = $pdo->query(
        "SELECT {$rgroup} as lbl, COUNT(*) as cnt 
         FROM orders {$rwhere_orders} 
         GROUP BY {$rgroup} ORDER BY {$rgroup}"
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── Top pages ─────────────────────────────────────────────────────────────
    $topPages = $pdo->query(
        "SELECT page, COUNT(*) as cnt FROM traffic_logs {$rwhere} 
         GROUP BY page ORDER BY cnt DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── Top IPs ───────────────────────────────────────────────────────────────
    $topIPs = $pdo->query(
        "SELECT ip_address, COUNT(*) as cnt FROM traffic_logs {$rwhere} 
         GROUP BY ip_address ORDER BY cnt DESC LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── Action breakdown ──────────────────────────────────────────────────────
    $actions = $pdo->query(
        "SELECT action, COUNT(*) as cnt FROM traffic_logs GROUP BY action ORDER BY cnt DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── Log table ─────────────────────────────────────────────────────────────
    $where   = $filter !== 'all' ? "WHERE action = " . $pdo->quote($filter) : '';
    $total   = (int) $pdo->query("SELECT COUNT(*) FROM traffic_logs {$where}")->fetchColumn();
    $logs    = $pdo->query("SELECT * FROM traffic_logs {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);

    // ── Peak hour (always hourly) ─────────────────────────────────────────────
    $peakRow = $pdo->query(
        "SELECT HOUR(created_at) as h, COUNT(*) as cnt FROM traffic_logs 
         WHERE created_at >= NOW() - INTERVAL 24 HOUR 
         GROUP BY HOUR(created_at) ORDER BY cnt DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    $peakHour = $peakRow ? $peakRow['h'].':00' : '-';

} catch(\Exception $e) {
    $logs = $actions = $trafficChart = $ordersChart = $topPages = $topIPs = [];
    $total = $totalHits = $uniqueIps = $orderHits = $convRate = 0;
    $totalOrders = $totalRevenue = $pendingOrders = 0;
    $peakHour = '-'; $rtitle = 'Hari Ini';
}

$totalPages = max(1, (int) ceil($total / $perPage));
$pageTitle  = 'Traffic & Stats';
$activePage = 'traffic';
require __DIR__ . '/partials/header.php';

// Helper: format Rupiah
function fRp(int $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>

<style>
/* ── Traffic page extras ────────────────────────────────────────────────── */
.range-bar{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:20px}
.range-btn{padding:6px 16px;border-radius:20px;font-size:13px;font-weight:500;border:1.5px solid var(--c-border);background:transparent;color:var(--c-text-sec);cursor:pointer;transition:.15s}
.range-btn.active,.range-btn:hover{background:var(--c-blue);border-color:var(--c-blue);color:#fff}
.metric-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;margin-bottom:20px}
.metric-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:12px;padding:16px 18px;position:relative;overflow:hidden;transition:.2s}
.metric-card:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.12)}
.metric-card__accent{position:absolute;top:0;left:0;width:3px;height:100%;border-radius:3px 0 0 3px}
.metric-card__label{font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--c-text-hint);margin-bottom:8px}
.metric-card__value{font-size:26px;font-weight:700;line-height:1;color:var(--c-text)}
.metric-card__sub{font-size:11px;color:var(--c-text-hint);margin-top:4px}
.metric-card__icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);opacity:.08;font-size:40px}

.chart-wrap{background:var(--c-surface);border:1px solid var(--c-border);border-radius:12px;padding:20px;margin-bottom:16px}
.chart-wrap__header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px}
.chart-wrap__title{font-size:15px;font-weight:600;color:var(--c-text)}
.chart-legend{display:flex;gap:14px;flex-wrap:wrap}
.chart-legend__item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--c-text-sec)}
.chart-legend__dot{width:10px;height:10px;border-radius:50%}

.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
@media(max-width:768px){.two-col{grid-template-columns:1fr}}

.mini-list{list-style:none;padding:0;margin:0}
.mini-list li{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--c-border);font-size:13px}
.mini-list li:last-child{border-bottom:none}
.mini-list__bar{flex:1;background:var(--c-border);border-radius:4px;height:6px;overflow:hidden}
.mini-list__fill{height:100%;border-radius:4px;background:var(--c-blue);transition:.4s}
.mini-list__label{flex:0 0 130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--c-text-sec);font-family:monospace;font-size:12px}
.mini-list__count{flex:0 0 42px;text-align:right;font-weight:600;color:var(--c-text)}

.action-tag{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;background:var(--c-surface);border:1px solid var(--c-border);color:var(--c-text-sec);cursor:pointer;text-decoration:none;transition:.15s}
.action-tag:hover,.action-tag.active{background:var(--c-blue);border-color:var(--c-blue);color:#fff}
.action-tag .cnt{font-size:10px;opacity:.7}

.log-table{width:100%;border-collapse:collapse}
.log-table th{padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.4px;text-transform:uppercase;color:var(--c-text-hint);border-bottom:1px solid var(--c-border);white-space:nowrap}
.log-table td{padding:9px 14px;border-bottom:1px solid var(--c-border);font-size:13px;vertical-align:middle}
.log-table tr:last-child td{border-bottom:none}
.log-table tr:hover td{background:var(--c-surface-hover,rgba(255,255,255,.03))}
.log-table .time-col{white-space:nowrap;font-size:11px;color:var(--c-text-hint);font-family:monospace}
.log-table .ip-col{font-family:monospace;font-size:12px;color:var(--c-text-sec)}
.log-table .page-col code{font-size:11px;padding:2px 6px;background:rgba(66,133,244,.1);color:#4285F4;border-radius:4px}
.log-table .data-col{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:var(--c-text-hint)}

.badge-action{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.badge-action.order_created{background:rgba(52,211,153,.15);color:#10b981}
.badge-action.page_view{background:rgba(66,133,244,.12);color:#4285F4}
.badge-action.checkout_start{background:rgba(251,188,4,.15);color:#f59e0b}
</style>

<div class="page-header" style="margin-bottom:8px">
  <div>
    <h1 class="page-title">Traffic & Analytics</h1>
    <p class="page-sub">Monitor aktivitas pengunjung & order secara real-time</p>
  </div>
  <div style="font-size:12px;color:var(--c-text-hint);padding:6px 12px;background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px">
    🔴 Live · <?= date('d M Y, H:i') ?>
  </div>
</div>

<!-- Range Selector -->
<div class="range-bar">
  <span style="font-size:12px;color:var(--c-text-hint);font-weight:500">Rentang:</span>
  <?php
  $ranges = ['jam'=>'24 Jam','day'=>'Hari Ini','week'=>'Minggu Ini','month'=>'30 Hari','6month'=>'6 Bulan'];
  foreach ($ranges as $k=>$v):
  ?>
  <a href="?range=<?= $k ?>&action=<?= urlencode($filter) ?>" class="range-btn <?= $range===$k?'active':'' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<!-- Metric Cards -->
<div class="metric-grid">
  <div class="metric-card">
    <div class="metric-card__accent" style="background:#4285F4"></div>
    <div class="metric-card__label">Total Hits</div>
    <div class="metric-card__value"><?= number_format($totalHits) ?></div>
    <div class="metric-card__sub"><?= $rtitle ?></div>
    <div class="metric-card__icon">📈</div>
  </div>
  <div class="metric-card">
    <div class="metric-card__accent" style="background:#34A853"></div>
    <div class="metric-card__label">IP Unik</div>
    <div class="metric-card__value"><?= number_format($uniqueIps) ?></div>
    <div class="metric-card__sub">Pengunjung berbeda</div>
    <div class="metric-card__icon">🌐</div>
  </div>
  <div class="metric-card">
    <div class="metric-card__accent" style="background:#FBBC04"></div>
    <div class="metric-card__label">Total Orders</div>
    <div class="metric-card__value"><?= number_format($totalOrders) ?></div>
    <div class="metric-card__sub"><?= $pendingOrders ?> pending</div>
    <div class="metric-card__icon">🛒</div>
  </div>
  <div class="metric-card">
    <div class="metric-card__accent" style="background:#34A853"></div>
    <div class="metric-card__label">Revenue</div>
    <div class="metric-card__value" style="font-size:18px"><?= fRp($totalRevenue) ?></div>
    <div class="metric-card__sub">Confirmed orders</div>
    <div class="metric-card__icon">💰</div>
  </div>
  <div class="metric-card">
    <div class="metric-card__accent" style="background:#EA4335"></div>
    <div class="metric-card__label">Conversion</div>
    <div class="metric-card__value"><?= $convRate ?>%</div>
    <div class="metric-card__sub"><?= $orderHits ?> dari <?= number_format($totalHits) ?> hits</div>
    <div class="metric-card__icon">⚡</div>
  </div>
  <div class="metric-card">
    <div class="metric-card__accent" style="background:#9b59b6"></div>
    <div class="metric-card__label">Peak Hour</div>
    <div class="metric-card__value" style="font-size:22px"><?= $peakHour ?></div>
    <div class="metric-card__sub">Paling ramai hari ini</div>
    <div class="metric-card__icon">🕐</div>
  </div>
</div>

<!-- Combined Chart -->
<div class="chart-wrap">
  <div class="chart-wrap__header">
    <div class="chart-wrap__title">📊 Traffic vs Orders — <?= htmlspecialchars($rtitle) ?></div>
    <div class="chart-legend">
      <div class="chart-legend__item"><div class="chart-legend__dot" style="background:#4285F4"></div>Traffic Hits</div>
      <div class="chart-legend__item"><div class="chart-legend__dot" style="background:#34A853"></div>Orders</div>
    </div>
  </div>
  <canvas id="mainChart" height="<?= $range==='6month'?'100':'90' ?>"></canvas>
</div>

<!-- Top Pages + Top IPs -->
<div class="two-col">
  <div class="chart-wrap" style="margin-bottom:0">
    <div class="chart-wrap__header"><div class="chart-wrap__title">🔗 Top Halaman</div></div>
    <?php $maxPage = $topPages ? max(array_column($topPages,'cnt')) : 1; ?>
    <ul class="mini-list">
      <?php foreach ($topPages as $tp): ?>
      <li>
        <div class="mini-list__label" title="<?= htmlspecialchars($tp['page']) ?>"><?= htmlspecialchars($tp['page']) ?></div>
        <div class="mini-list__bar"><div class="mini-list__fill" style="width:<?= round(($tp['cnt']/$maxPage)*100) ?>%"></div></div>
        <div class="mini-list__count"><?= number_format($tp['cnt']) ?></div>
      </li>
      <?php endforeach; ?>
      <?php if(empty($topPages)): ?><li style="color:var(--c-text-hint)">Belum ada data</li><?php endif; ?>
    </ul>
  </div>
  <div class="chart-wrap" style="margin-bottom:0">
    <div class="chart-wrap__header"><div class="chart-wrap__title">📡 Top IP Address</div></div>
    <?php $maxIp = $topIPs ? max(array_column($topIPs,'cnt')) : 1; ?>
    <ul class="mini-list">
      <?php foreach ($topIPs as $ip): ?>
      <li>
        <div class="mini-list__label" title="<?= htmlspecialchars($ip['ip_address']) ?>"><?= htmlspecialchars($ip['ip_address']) ?></div>
        <div class="mini-list__bar"><div class="mini-list__fill" style="background:#EA4335;width:<?= round(($ip['cnt']/$maxIp)*100) ?>%"></div></div>
        <div class="mini-list__count"><?= number_format($ip['cnt']) ?></div>
      </li>
      <?php endforeach; ?>
      <?php if(empty($topIPs)): ?><li style="color:var(--c-text-hint)">Belum ada data</li><?php endif; ?>
    </ul>
  </div>
</div>

<!-- Log table -->
<div class="chart-wrap" style="margin-top:16px">
  <div class="chart-wrap__header">
    <div class="chart-wrap__title">📋 Log Aktivitas</div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a href="?range=<?= $range ?>&action=all" class="action-tag <?= $filter==='all'?'active':'' ?>">Semua</a>
      <?php foreach ($actions as $ac): ?>
      <a href="?range=<?= $range ?>&action=<?= urlencode($ac['action']??'') ?>" class="action-tag <?= $filter===$ac['action']?'active':'' ?>">
        <?= htmlspecialchars($ac['action']??'null') ?> <span class="cnt">(<?= $ac['cnt'] ?>)</span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <div style="overflow-x:auto">
    <table class="log-table">
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
          <td class="time-col"><?= date('d/m H:i:s', strtotime($log['created_at'])) ?></td>
          <td class="page-col"><code><?= htmlspecialchars($log['page'] ?? '-') ?></code></td>
          <td>
            <span class="badge-action <?= htmlspecialchars($log['action']??'') ?>">
              <?= htmlspecialchars($log['action'] ?? '-') ?>
            </span>
          </td>
          <td class="ip-col"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
          <td class="data-col" title="<?= htmlspecialchars($log['data'] ?? '') ?>">
            <?= $log['data'] ? htmlspecialchars(substr($log['data'], 0, 80)) : '-' ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="5" style="text-align:center;padding:36px;color:var(--c-text-hint)">Belum ada log</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination" style="margin-top:12px">
  <?php for ($p = 1; $p <= min($totalPages, 12); $p++): ?>
  <a href="?range=<?= $range ?>&action=<?= urlencode($filter) ?>&page=<?= $p ?>"
     class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
  <?php endfor; ?>
  <?php if ($totalPages > 12): ?>
  <span style="color:var(--c-text-hint);padding:0 8px">… <?= $totalPages ?> halaman</span>
  <?php endif; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Data dari PHP ────────────────────────────────────────────────────────────
const trafficRaw = <?= json_encode($trafficChart) ?>;
const ordersRaw  = <?= json_encode($ordersChart) ?>;
const currentRange = <?= json_encode($range) ?>;

// ── Build unified label set ───────────────────────────────────────────────────
function buildLabels() {
  if (currentRange === 'day' || currentRange === 'jam') {
    return Array.from({length:24}, (_,i) => String(i).padStart(2,'0')+':00');
  }
  // For week/month/6month merge keys from both datasets
  const keys = new Set();
  trafficRaw.forEach(r => keys.add(String(r.lbl)));
  ordersRaw.forEach(r => keys.add(String(r.lbl)));
  return Array.from(keys).sort();
}

function mapToLabels(labels, raw) {
  const map = {};
  raw.forEach(r => { map[String(r.lbl)] = parseInt(r.cnt); });
  return labels.map(l => map[l] ?? 0);
}

const labels    = buildLabels();
const trafficArr = mapToLabels(labels, trafficRaw);
const ordersArr  = mapToLabels(labels, ordersRaw);

// ── Gradient factory ──────────────────────────────────────────────────────────
function makeGradient(ctx, color1, color2) {
  const g = ctx.createLinearGradient(0, 0, 0, 300);
  g.addColorStop(0, color1);
  g.addColorStop(1, color2);
  return g;
}

const canvas = document.getElementById('mainChart');
const ctx    = canvas.getContext('2d');

new Chart(ctx, {
  type: 'line',
  data: {
    labels,
    datasets: [
      {
        label: 'Traffic Hits',
        data: trafficArr,
        borderColor: '#4285F4',
        backgroundColor: makeGradient(ctx, 'rgba(66,133,244,0.25)', 'rgba(66,133,244,0.01)'),
        fill: true,
        tension: 0.45,
        pointRadius: labels.length <= 24 ? 4 : 3,
        pointHoverRadius: 7,
        pointBackgroundColor: '#4285F4',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        borderWidth: 2.5,
        yAxisID: 'yTraffic',
      },
      {
        label: 'Orders',
        data: ordersArr,
        borderColor: '#34A853',
        backgroundColor: makeGradient(ctx, 'rgba(52,168,83,0.2)', 'rgba(52,168,83,0.01)'),
        fill: true,
        tension: 0.45,
        pointRadius: labels.length <= 24 ? 4 : 3,
        pointHoverRadius: 7,
        pointBackgroundColor: '#34A853',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        borderWidth: 2.5,
        yAxisID: 'yOrders',
      }
    ]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(15,15,25,0.92)',
        titleColor: '#e0e0e0',
        bodyColor: '#a0a0b0',
        borderColor: 'rgba(255,255,255,0.08)',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 10,
        callbacks: {
          title: (items) => '⏱ ' + items[0].label,
          label: (item) => {
            const icon = item.datasetIndex === 0 ? '📈' : '🛒';
            return ` ${icon} ${item.dataset.label}: ${item.formattedValue}`;
          }
        }
      }
    },
    scales: {
      x: {
        grid: { color: 'rgba(255,255,255,0.04)' },
        ticks: {
          color: '#6b7280',
          font: { size: 11 },
          maxTicksLimit: currentRange === '6month' ? 6 : (currentRange === 'month' ? 10 : 12),
          maxRotation: 30,
        }
      },
      yTraffic: {
        position: 'left',
        beginAtZero: true,
        grid: { color: 'rgba(255,255,255,0.05)' },
        ticks: { color: '#4285F4', font: { size: 11 }, precision: 0 },
        title: { display: true, text: 'Hits', color: '#4285F4', font:{size:11} }
      },
      yOrders: {
        position: 'right',
        beginAtZero: true,
        grid: { drawOnChartArea: false },
        ticks: { color: '#34A853', font: { size: 11 }, precision: 0 },
        title: { display: true, text: 'Orders', color: '#34A853', font:{size:11} }
      }
    }
  }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
