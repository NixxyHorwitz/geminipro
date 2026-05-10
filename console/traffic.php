<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

$range   = $_GET['range']  ?? 'day';
$dateStr = trim($_GET['date'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;
$offset  = ($page - 1) * $perPage;
$filter  = $_GET['action'] ?? 'all';

$validRanges = ['jam','day','week','month','6month'];
if (!in_array($range, $validRanges, true)) $range = 'day';

$useDate   = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr);
$prevDate  = $nextDate = '';
$chartMode = 'hourly';

if ($useDate) {
    $rwhere   = "WHERE DATE(created_at) = " . $pdo->quote($dateStr);
    $rgroup   = "HOUR(created_at)";
    $rtitle   = "Tanggal " . date('d M Y', strtotime($dateStr));
    $prevDate = date('Y-m-d', strtotime($dateStr . ' -1 day'));
    $nextDate = date('Y-m-d', strtotime($dateStr . ' +1 day'));
} else {
    switch ($range) {
        case 'jam':
            $rwhere = "WHERE created_at >= NOW() - INTERVAL 24 HOUR";
            $rgroup = "HOUR(created_at)"; $rtitle = "24 Jam Terakhir"; $chartMode = 'hourly'; break;
        case 'week':
            $rwhere = "WHERE created_at >= CURDATE() - INTERVAL 6 DAY";
            $rgroup = "DATE(created_at)"; $rtitle = "Minggu Ini"; $chartMode = 'daily'; break;
        case 'month':
            $rwhere = "WHERE created_at >= CURDATE() - INTERVAL 29 DAY";
            $rgroup = "DATE(created_at)"; $rtitle = "30 Hari"; $chartMode = 'daily'; break;
        case '6month':
            $rwhere = "WHERE created_at >= CURDATE() - INTERVAL 180 DAY";
            $rgroup = "DATE_FORMAT(created_at,'%Y-%m')"; $rtitle = "6 Bulan"; $chartMode = 'monthly'; break;
        default:
            $rwhere = "WHERE DATE(created_at)=CURDATE()";
            $rgroup = "HOUR(created_at)"; $rtitle = "Hari Ini"; $chartMode = 'hourly'; break;
    }
}

try {
    $totalHits    = (int)$pdo->query("SELECT COUNT(*) FROM traffic_logs {$rwhere}")->fetchColumn();
    $uniqueIps    = (int)$pdo->query("SELECT COUNT(DISTINCT ip_address) FROM traffic_logs {$rwhere}")->fetchColumn();
    $orderHits    = (int)$pdo->query("SELECT COUNT(*) FROM traffic_logs {$rwhere} AND action='order_created'")->fetchColumn();
    $convRate     = $totalHits > 0 ? round(($orderHits / $totalHits) * 100, 2) : 0;

    $totalOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders {$rwhere}")->fetchColumn();
    $totalRevenue = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders {$rwhere} AND status='confirmed'")->fetchColumn();
    $pendingOrders= (int)$pdo->query("SELECT COUNT(*) FROM orders {$rwhere} AND status='pending'")->fetchColumn();

    $trafficChart = $pdo->query(
        "SELECT {$rgroup} as lbl, COUNT(*) as cnt FROM traffic_logs {$rwhere} GROUP BY {$rgroup} ORDER BY {$rgroup}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $ordersChart = $pdo->query(
        "SELECT {$rgroup} as lbl, COUNT(*) as cnt FROM orders {$rwhere} GROUP BY {$rgroup} ORDER BY {$rgroup}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $topPages = $pdo->query(
        "SELECT page, COUNT(*) as cnt FROM traffic_logs {$rwhere} GROUP BY page ORDER BY cnt DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);

    $topIPs = $pdo->query(
        "SELECT ip_address, COUNT(*) as cnt FROM traffic_logs {$rwhere} GROUP BY ip_address ORDER BY cnt DESC LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);

    $actions = $pdo->query(
        "SELECT action, COUNT(*) as cnt FROM traffic_logs GROUP BY action ORDER BY cnt DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $logWhere = $filter !== 'all' ? "WHERE action = " . $pdo->quote($filter) : '';
    $total    = (int)$pdo->query("SELECT COUNT(*) FROM traffic_logs {$logWhere}")->fetchColumn();
    $logs     = $pdo->query("SELECT * FROM traffic_logs {$logWhere} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);

    $peakRow  = $pdo->query(
        "SELECT HOUR(created_at) as h, COUNT(*) as cnt FROM traffic_logs
         WHERE created_at >= NOW() - INTERVAL 24 HOUR
         GROUP BY HOUR(created_at) ORDER BY cnt DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    $peakHour = $peakRow ? str_pad((string)$peakRow['h'], 2, '0', STR_PAD_LEFT).':00' : '-';

} catch(\Exception $e) {
    $logs=$actions=$trafficChart=$ordersChart=$topPages=$topIPs=[];
    $total=$totalHits=$uniqueIps=$orderHits=$convRate=0;
    $totalOrders=$totalRevenue=$pendingOrders=0;
    $peakHour='-'; $rtitle='Hari Ini';
}

$totalPages = max(1, (int)ceil($total / $perPage));
$pageTitle  = 'Traffic & Stats';
$activePage = 'traffic';
require __DIR__ . '/partials/header.php';

function fRp(int $n): string { return 'Rp '.number_format($n,0,',','.'); }
?>
<style>
.tf-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.range-bar{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.range-btn{padding:5px 14px;border-radius:20px;font-size:12px;font-weight:500;border:1.5px solid var(--c-border);background:transparent;color:var(--c-text-sec);cursor:pointer;transition:.15s;text-decoration:none;display:inline-block}
.range-btn.active,.range-btn:hover{background:#4285F4;border-color:#4285F4;color:#fff}
.date-nav{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.date-nav input[type=date]{padding:5px 10px;border-radius:8px;border:1.5px solid var(--c-border);background:var(--c-surface);color:var(--c-text);font-size:13px}
.nav-btn{padding:5px 10px;border-radius:8px;border:1.5px solid var(--c-border);background:var(--c-surface);color:var(--c-text-sec);font-size:13px;transition:.15s;text-decoration:none;display:inline-flex;align-items:center}
.nav-btn:hover{border-color:#4285F4;color:#4285F4}
.metric-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:16px}
.mc{background:var(--c-surface);border:1px solid var(--c-border);border-radius:12px;padding:16px;position:relative;overflow:hidden;transition:.2s}
.mc:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.12)}
.mc__bar{position:absolute;top:0;left:0;width:3px;height:100%}
.mc__lbl{font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--c-text-hint);margin-bottom:8px}
.mc__val{font-size:24px;font-weight:700;line-height:1;color:var(--c-text)}
.mc__sub{font-size:11px;color:var(--c-text-hint);margin-top:4px}
.chart-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:12px;padding:20px;margin-bottom:14px}
.chart-card__hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px}
.chart-card__ttl{font-size:14px;font-weight:600;color:var(--c-text)}
.legend{display:flex;gap:12px}
.legend i{width:10px;height:10px;border-radius:50%;display:inline-block}
.legend span{font-size:12px;color:var(--c-text-sec);display:flex;align-items:center;gap:5px}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:700px){.two-col{grid-template-columns:1fr}}
.mlist{list-style:none;padding:0;margin:0}
.mlist li{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--c-border);font-size:12px}
.mlist li:last-child{border-bottom:none}
.mlist__lbl{flex:0 0 120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--c-text-sec);font-family:monospace;font-size:11px}
.mlist__bar{flex:1;background:var(--c-border);border-radius:4px;height:5px;overflow:hidden}
.mlist__fill{height:100%;border-radius:4px;transition:.4s}
.mlist__cnt{flex:0 0 38px;text-align:right;font-weight:600;color:var(--c-text);font-size:12px}
.atag{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;background:var(--c-surface);border:1px solid var(--c-border);color:var(--c-text-sec);text-decoration:none;transition:.15s}
.atag:hover,.atag.active{background:#4285F4;border-color:#4285F4;color:#fff}
.ltable{width:100%;border-collapse:collapse}
.ltable th{padding:9px 14px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--c-text-hint);border-bottom:1px solid var(--c-border);white-space:nowrap}
.ltable td{padding:8px 14px;border-bottom:1px solid var(--c-border);font-size:12px;vertical-align:middle}
.ltable tr:last-child td{border-bottom:none}
.ltable tr:hover td{background:rgba(66,133,244,.03)}
.bact{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:rgba(66,133,244,.12);color:#4285F4}
.bact.order_created{background:rgba(52,211,153,.15);color:#10b981}
.bact.checkout_start{background:rgba(251,188,4,.15);color:#f59e0b}
</style>

<div class="tf-header">
  <div>
    <h1 class="page-title">Traffic &amp; Analytics</h1>
    <p class="page-sub">Monitor aktivitas pengunjung &amp; order — <?= htmlspecialchars($rtitle) ?></p>
  </div>
  <div style="font-size:11px;color:var(--c-text-hint);padding:5px 11px;background:var(--c-surface);border:1px solid var(--c-border);border-radius:8px">
    🔴 Live · <?= date('d M Y, H:i') ?>
  </div>
</div>

<!-- Range + Date Nav -->
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:16px">
  <div class="range-bar">
    <?php $ranges=['jam'=>'24 Jam','day'=>'Hari Ini','week'=>'Minggu','month'=>'30 Hari','6month'=>'6 Bulan'];
    foreach ($ranges as $k=>$v): ?>
    <a href="?range=<?=$k?>&amp;action=<?=urlencode($filter)?>" class="range-btn <?=(!$useDate&&$range===$k)?'active':''?>"><?=$v?></a>
    <?php endforeach; ?>
  </div>
  <div class="date-nav">
    <?php if($useDate): ?>
    <a href="?date=<?=$prevDate?>&amp;action=<?=urlencode($filter)?>" class="nav-btn">&#8592; Prev</a>
    <?php endif; ?>
    <form method="GET" style="display:inline">
      <input type="hidden" name="action" value="<?=htmlspecialchars($filter)?>">
      <input type="date" name="date" value="<?=htmlspecialchars($dateStr)?>" onchange="this.form.submit()"
             max="<?=date('Y-m-d')?>" <?=$useDate?'style="border-color:#4285F4"':''?>>
    </form>
    <?php if($useDate && $nextDate <= date('Y-m-d')): ?>
    <a href="?date=<?=$nextDate?>&amp;action=<?=urlencode($filter)?>" class="nav-btn">Next &#8594;</a>
    <?php endif; ?>
    <?php if($useDate): ?>
    <a href="?range=day&amp;action=<?=urlencode($filter)?>" class="nav-btn" style="font-size:11px">&#10005; Reset</a>
    <?php endif; ?>
  </div>
</div>

<!-- Metric Cards -->
<div class="metric-grid">
  <div class="mc"><div class="mc__bar" style="background:#4285F4"></div>
    <div class="mc__lbl">Total Hits</div><div class="mc__val"><?=number_format($totalHits)?></div>
    <div class="mc__sub"><?=htmlspecialchars($rtitle)?></div></div>
  <div class="mc"><div class="mc__bar" style="background:#34A853"></div>
    <div class="mc__lbl">IP Unik</div><div class="mc__val"><?=number_format($uniqueIps)?></div>
    <div class="mc__sub">Pengunjung berbeda</div></div>
  <div class="mc"><div class="mc__bar" style="background:#FBBC04"></div>
    <div class="mc__lbl">Total Orders</div><div class="mc__val"><?=number_format($totalOrders)?></div>
    <div class="mc__sub"><?=$pendingOrders?> pending</div></div>
  <div class="mc"><div class="mc__bar" style="background:#34A853"></div>
    <div class="mc__lbl">Revenue</div><div class="mc__val" style="font-size:17px"><?=fRp($totalRevenue)?></div>
    <div class="mc__sub">Confirmed</div></div>
  <div class="mc"><div class="mc__bar" style="background:#EA4335"></div>
    <div class="mc__lbl">Conversion</div><div class="mc__val"><?=$convRate?>%</div>
    <div class="mc__sub"><?=$orderHits?> / <?=number_format($totalHits)?></div></div>
  <div class="mc"><div class="mc__bar" style="background:#9b59b6"></div>
    <div class="mc__lbl">Peak Hour</div><div class="mc__val" style="font-size:20px"><?=$peakHour?></div>
    <div class="mc__sub">Paling ramai hari ini</div></div>
</div>

<!-- Combined Chart -->
<div class="chart-card">
  <div class="chart-card__hd">
    <div class="chart-card__ttl">&#128202; Traffic vs Orders &#8212; <?=htmlspecialchars($rtitle)?></div>
    <div class="legend">
      <span><i style="background:#4285F4"></i>Traffic Hits</span>
      <span><i style="background:#34A853"></i>Orders</span>
    </div>
  </div>
  <div style="position:relative;height:300px">
    <canvas id="mainChart"></canvas>
  </div>
</div>

<!-- Top Pages + Top IPs -->
<div class="two-col">
  <div class="chart-card" style="margin-bottom:0">
    <div class="chart-card__hd"><div class="chart-card__ttl">&#128279; Top Halaman</div></div>
    <?php $mx=max(1,...(array_column($topPages,'cnt')?:[1])); ?>
    <ul class="mlist">
      <?php foreach($topPages as $r): ?>
      <li><div class="mlist__lbl" title="<?=htmlspecialchars($r['page'])?>"><?=htmlspecialchars($r['page'])?></div>
        <div class="mlist__bar"><div class="mlist__fill" style="background:#4285F4;width:<?=round(($r['cnt']/$mx)*100)?>%"></div></div>
        <div class="mlist__cnt"><?=number_format($r['cnt'])?></div></li>
      <?php endforeach; if(empty($topPages)):?><li style="color:var(--c-text-hint)">Belum ada data</li><?php endif;?>
    </ul>
  </div>
  <div class="chart-card" style="margin-bottom:0">
    <div class="chart-card__hd"><div class="chart-card__ttl">&#128225; Top IP Address</div></div>
    <?php $mx2=max(1,...(array_column($topIPs,'cnt')?:[1])); ?>
    <ul class="mlist">
      <?php foreach($topIPs as $r): ?>
      <li><div class="mlist__lbl" title="<?=htmlspecialchars($r['ip_address'])?>"><?=htmlspecialchars($r['ip_address'])?></div>
        <div class="mlist__bar"><div class="mlist__fill" style="background:#EA4335;width:<?=round(($r['cnt']/$mx2)*100)?>%"></div></div>
        <div class="mlist__cnt"><?=number_format($r['cnt'])?></div></li>
      <?php endforeach; if(empty($topIPs)):?><li style="color:var(--c-text-hint)">Belum ada data</li><?php endif;?>
    </ul>
  </div>
</div>

<!-- Log Table -->
<div class="chart-card">
  <div class="chart-card__hd">
    <div class="chart-card__ttl">&#128203; Log Aktivitas</div>
    <div style="display:flex;gap:5px;flex-wrap:wrap">
      <a href="?range=<?=$range?>&amp;date=<?=urlencode($dateStr)?>&amp;action=all" class="atag <?=$filter==='all'?'active':''?>">Semua</a>
      <?php foreach($actions as $ac): ?>
      <a href="?range=<?=$range?>&amp;date=<?=urlencode($dateStr)?>&amp;action=<?=urlencode($ac['action']??'')?>" class="atag <?=$filter===$ac['action']?'active':''?>">
        <?=htmlspecialchars($ac['action']??'null')?> <small>(<?=$ac['cnt']?>)</small></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div style="overflow-x:auto">
    <table class="ltable">
      <thead><tr><th>Waktu</th><th>Halaman</th><th>Aksi</th><th>IP</th><th>Data</th></tr></thead>
      <tbody>
        <?php foreach($logs as $log): ?>
        <tr>
          <td style="white-space:nowrap;font-family:monospace;color:var(--c-text-hint)"><?=date('d/m H:i:s',strtotime($log['created_at']))?></td>
          <td><code style="font-size:11px;padding:2px 6px;background:rgba(66,133,244,.1);color:#4285F4;border-radius:4px"><?=htmlspecialchars($log['page']??'-')?></code></td>
          <td><span class="bact <?=htmlspecialchars($log['action']??'')?>"><?=htmlspecialchars($log['action']??'-')?></span></td>
          <td style="font-family:monospace;font-size:11px;color:var(--c-text-sec)"><?=htmlspecialchars($log['ip_address']??'-')?></td>
          <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:var(--c-text-hint)" title="<?=htmlspecialchars($log['data']??'')?>">
            <?=$log['data']?htmlspecialchars(substr($log['data'],0,80)):'-'?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($logs)): ?>
        <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--c-text-hint)">Belum ada log</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if($totalPages>1): ?>
<div class="pagination" style="margin-top:10px">
  <?php for($p=1;$p<=min($totalPages,12);$p++): ?>
  <a href="?range=<?=$range?>&amp;date=<?=urlencode($dateStr)?>&amp;action=<?=urlencode($filter)?>&amp;page=<?=$p?>"
     class="page-link <?=$p===$page?'active':''?>"><?=$p?></a>
  <?php endfor; ?>
  <?php if($totalPages>12): ?><span style="color:var(--c-text-hint);padding:0 8px">&#8230;<?=$totalPages?></span><?php endif; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
  var tRaw = <?= json_encode($trafficChart) ?>;
  var oRaw = <?= json_encode($ordersChart) ?>;
  var mode = <?= json_encode($chartMode) ?>;

  var labels, tArr, oArr;
  if (mode === 'hourly') {
    labels = [];
    for (var i = 0; i < 24; i++) { labels.push((i < 10 ? '0' : '') + i + ':00'); }
    tArr = new Array(24).fill(0);
    oArr = new Array(24).fill(0);
    for (var j = 0; j < tRaw.length; j++) { tArr[parseInt(tRaw[j].lbl)] = parseInt(tRaw[j].cnt); }
    for (var k = 0; k < oRaw.length; k++) { oArr[parseInt(oRaw[k].lbl)] = parseInt(oRaw[k].cnt); }
  } else {
    var keySet = {};
    for (var a = 0; a < tRaw.length; a++) { keySet[String(tRaw[a].lbl)] = 1; }
    for (var b = 0; b < oRaw.length; b++) { keySet[String(oRaw[b].lbl)] = 1; }
    labels = Object.keys(keySet).sort();
    var tm = {}, om = {};
    for (var c = 0; c < tRaw.length; c++) { tm[String(tRaw[c].lbl)] = parseInt(tRaw[c].cnt); }
    for (var d = 0; d < oRaw.length; d++) { om[String(oRaw[d].lbl)] = parseInt(oRaw[d].cnt); }
    tArr = labels.map(function(l){ return tm[l] || 0; });
    oArr = labels.map(function(l){ return om[l] || 0; });
  }

  var canvas = document.getElementById('mainChart');
  new Chart(canvas, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Traffic Hits',
          data: tArr,
          borderColor: '#4285F4',
          backgroundColor: 'rgba(66,133,244,0.15)',
          fill: true,
          tension: 0.4,
          pointRadius: 4,
          pointHoverRadius: 7,
          pointBackgroundColor: '#4285F4',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          borderWidth: 2.5
        },
        {
          label: 'Orders',
          data: oArr,
          borderColor: '#34A853',
          backgroundColor: 'rgba(52,168,83,0.12)',
          fill: true,
          tension: 0.4,
          pointRadius: 4,
          pointHoverRadius: 7,
          pointBackgroundColor: '#34A853',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          borderWidth: 2.5,
          yAxisID: 'y2'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(15,15,25,0.92)',
          titleColor: '#e0e0e0',
          bodyColor: '#aaa',
          borderColor: 'rgba(255,255,255,0.08)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 10
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(128,128,128,0.08)' },
          ticks: { color: '#6b7280', font: { size: 11 }, maxTicksLimit: 12, maxRotation: 30 }
        },
        y: {
          beginAtZero: true,
          position: 'left',
          grid: { color: 'rgba(128,128,128,0.06)' },
          ticks: { color: '#4285F4', font: { size: 11 }, precision: 0 }
        },
        y2: {
          beginAtZero: true,
          position: 'right',
          grid: { drawOnChartArea: false },
          ticks: { color: '#34A853', font: { size: 11 }, precision: 0 }
        }
      }
    }
  });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
