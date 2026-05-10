<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

$range   = $_GET['range']  ?? 'month';

$validRanges = ['jam','day','week','month','6month'];
if (!in_array($range, $validRanges, true)) $range = 'month';

switch ($range) {
    case 'jam':
        $rwhere = "WHERE created_at >= NOW() - INTERVAL 24 HOUR";
        $rgroup = "HOUR(created_at)"; $rtitle = "24 Jam Terakhir"; break;
    case 'day':
        $rwhere = "WHERE DATE(created_at)=CURDATE()";
        $rgroup = "HOUR(created_at)"; $rtitle = "Hari Ini"; break;
    case 'week':
        $rwhere = "WHERE created_at >= CURDATE() - INTERVAL 6 DAY";
        $rgroup = "DATE(created_at)"; $rtitle = "Minggu Ini"; break;
    case '6month':
        $rwhere = "WHERE created_at >= CURDATE() - INTERVAL 180 DAY";
        $rgroup = "DATE_FORMAT(created_at,'%Y-%m')"; $rtitle = "6 Bulan"; break;
    default: // month
        $rwhere = "WHERE created_at >= CURDATE() - INTERVAL 29 DAY";
        $rgroup = "DATE(created_at)"; $rtitle = "30 Hari"; break;
}

try {
    $totalHits    = (int)$pdo->query("SELECT COUNT(*) FROM traffic_logs {$rwhere}")->fetchColumn();
    $uniqueIps    = (int)$pdo->query("SELECT COUNT(DISTINCT ip_address) FROM traffic_logs {$rwhere}")->fetchColumn();

    // Untuk analytics order, kita butuh detail revenue success
    $orderHits    = (int)$pdo->query("SELECT COUNT(*) FROM traffic_logs {$rwhere} AND action='order_created'")->fetchColumn();
    $convRate     = $totalHits > 0 ? round(($orderHits / $totalHits) * 100, 2) : 0;

    $totalOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders {$rwhere}")->fetchColumn();
    // Revenue HANYA dari yang success / confirmed
    $totalRevenue = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders {$rwhere} AND status='confirmed'")->fetchColumn();
    $successOrders= (int)$pdo->query("SELECT COUNT(*) FROM orders {$rwhere} AND status='confirmed'")->fetchColumn();

    $trafficChart = $pdo->query(
        "SELECT {$rgroup} as lbl, COUNT(*) as cnt FROM traffic_logs {$rwhere} GROUP BY {$rgroup} ORDER BY {$rgroup}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $ordersChart = $pdo->query(
        "SELECT {$rgroup} as lbl, COUNT(*) as cnt, SUM(CASE WHEN status='confirmed' THEN amount ELSE 0 END) as rev 
         FROM orders {$rwhere} GROUP BY {$rgroup} ORDER BY {$rgroup}"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch(\Exception $e) {
    $trafficChart=$ordersChart=[];
    $totalHits=$uniqueIps=$orderHits=$convRate=0;
    $totalOrders=$totalRevenue=$successOrders=0;
}

$pageTitle  = 'Web Analytics';
$activePage = 'analytics';
require __DIR__ . '/partials/header.php';

function fRp(int $n): string { return 'Rp '.number_format($n,0,',','.'); }

// Mapping data for CSS Chart
$chartData = [];
foreach($trafficChart as $row) {
    $chartData[$row['lbl']] = ['t' => (int)$row['cnt'], 'o' => 0, 'r' => 0];
}
foreach($ordersChart as $row) {
    if (!isset($chartData[$row['lbl']])) $chartData[$row['lbl']] = ['t' => 0, 'o' => 0, 'r' => 0];
    $chartData[$row['lbl']]['o'] = (int)$row['cnt'];
    $chartData[$row['lbl']]['r'] = (int)$row['rev'];
}
ksort($chartData);

$maxT = max(1, ...array_column($chartData, 't') ?: [1]);
$maxO = max(1, ...array_column($chartData, 'o') ?: [1]);
$maxR = max(1, ...array_column($chartData, 'r') ?: [1]);
?>
<style>
.tf-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.range-bar{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.range-btn{padding:5px 14px;border-radius:20px;font-size:12px;font-weight:500;border:1.5px solid var(--c-border);background:transparent;color:var(--c-text-sec);cursor:pointer;transition:.15s;text-decoration:none;display:inline-block}
.range-btn.active,.range-btn:hover{background:#111;border-color:#111;color:#fff;box-shadow:2px 2px 0px #4285F4}
.metric-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:20px}
.mc{background:#fff;border:2px solid #111;border-radius:8px;padding:20px;position:relative;box-shadow:4px 4px 0px #111;transition:.2s}
.mc:hover{transform:translate(-2px,-2px);box-shadow:6px 6px 0px #111}
.mc__lbl{font-size:11px;font-weight:800;text-transform:uppercase;color:#555;margin-bottom:8px}
.mc__val{font-size:28px;font-weight:900;line-height:1;color:#111}
.mc__sub{font-size:12px;color:#777;margin-top:6px;font-weight:500}
.chart-card{background:#fff;border:2px solid #111;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:4px 4px 0px #111;overflow-x:auto}
.chart-card__hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:8px}
.chart-card__ttl{font-size:16px;font-weight:800;color:#111;text-transform:uppercase}
.css-chart{display:flex;align-items:flex-end;gap:8px;height:250px;padding-bottom:10px;border-bottom:2px solid #111;min-width:600px;margin-top:20px}
.css-col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;position:relative;group}
.css-bar-t{width:100%;background:#4285F4;border:2px solid #111;border-radius:4px 4px 0 0;position:relative;transition:.3s}
.css-bar-o{width:100%;background:#34A853;border:2px solid #111;border-radius:4px 4px 0 0;position:relative;transition:.3s;margin-top:-2px}
.css-col-lbl{font-size:10px;font-weight:700;color:#555;margin-top:8px;text-align:center;word-break:break-all}
.css-tooltip{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#111;color:#fff;padding:8px 10px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap;opacity:0;pointer-events:none;transition:.2s;z-index:10;margin-bottom:8px}
.css-col:hover .css-tooltip{opacity:1;margin-bottom:12px}
.css-tooltip::after{content:'';position:absolute;top:100%;left:50%;margin-left:-4px;border-width:4px;border-style:solid;border-color:#111 transparent transparent transparent}
</style>

<div class="tf-header">
  <div>
    <h1 class="page-title" style="font-weight:900;text-transform:uppercase">Analytics Web</h1>
    <p class="page-sub" style="font-weight:500;color:#555">Sistem pelacakan trafik, order, dan revenue tanpa delay.</p>
  </div>
</div>

<div class="range-bar" style="margin-bottom:20px">
  <?php $ranges=['jam'=>'24 Jam','day'=>'Hari Ini','week'=>'Minggu','month'=>'30 Hari','6month'=>'6 Bulan'];
  foreach ($ranges as $k=>$v): ?>
  <a href="?range=<?=$k?>" class="range-btn <?=($range===$k)?'active':''?>"><?=$v?></a>
  <?php endforeach; ?>
</div>

<div class="metric-grid">
  <div class="mc">
    <div class="mc__lbl">Total Kunjungan</div><div class="mc__val"><?=number_format($totalHits)?></div>
    <div class="mc__sub">IP Unik: <?=number_format($uniqueIps)?></div>
  </div>
  <div class="mc" style="background:#fef3c7">
    <div class="mc__lbl">Total Transaksi</div><div class="mc__val"><?=number_format($totalOrders)?></div>
    <div class="mc__sub">Pesanan dibuat (Pending+Success)</div>
  </div>
  <div class="mc" style="background:#d1fae5">
    <div class="mc__lbl">Transaksi Sukses</div><div class="mc__val"><?=number_format($successOrders)?></div>
    <div class="mc__sub">Conversion: <?=$convRate?>% dari visit</div>
  </div>
  <div class="mc" style="background:#e0e7ff;border-color:#3730a3">
    <div class="mc__lbl" style="color:#3730a3">Real Revenue</div>
    <div class="mc__val" style="color:#3730a3;font-size:24px"><?=fRp($totalRevenue)?></div>
    <div class="mc__sub">Dari transaksi berstatus Success</div>
  </div>
</div>

<!-- CSS Pure Chart -->
<div class="chart-card">
  <div class="chart-card__hd">
    <div class="chart-card__ttl">Visualisasi Trafik vs Revenue (<?=htmlspecialchars($rtitle)?>)</div>
    <div style="display:flex;gap:12px;font-size:12px;font-weight:700">
      <span style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:12px;background:#4285F4;border:2px solid #111;border-radius:3px"></span> Trafik</span>
      <span style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:12px;background:#34A853;border:2px solid #111;border-radius:3px"></span> Transaksi</span>
    </div>
  </div>

  <div class="css-chart">
    <?php foreach($chartData as $lbl => $data): 
        $hT = round(($data['t'] / $maxT) * 100);
        $hO = round(($data['o'] / $maxO) * 100);
    ?>
    <div class="css-col">
      <div class="css-tooltip">
        <?=htmlspecialchars($lbl)?><br>
        Trafik: <?=number_format($data['t'])?><br>
        Order: <?=number_format($data['o'])?><br>
        Revenue: <?=fRp($data['r'])?>
      </div>
      <div style="display:flex;width:100%;gap:2px;height:100%;align-items:flex-end">
        <div class="css-bar-t" style="height:<?=$hT?>%"></div>
        <div class="css-bar-o" style="height:<?=$hO?>%"></div>
      </div>
      <div class="css-col-lbl"><?=htmlspecialchars($lbl)?></div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($chartData)): ?>
      <div style="width:100%;text-align:center;font-weight:700;color:#777;padding-bottom:100px">Belum ada data di rentang waktu ini.</div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
