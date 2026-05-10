<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

$range   = $_GET['range']  ?? 'month';

$validRanges = ['jam','day','week','month','6month'];
if (!in_array($range, $validRanges, true)) $range = 'month';

$chartMode = 'daily';
$dateStr = $_GET['date'] ?? '';
$useDate = false;

if ($dateStr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
    $useDate = true;
    $chartMode = 'hourly';
    $rwhere = "WHERE DATE(created_at) = '$dateStr'";
    $rgroup = "HOUR(created_at)"; $rtitle = "Tanggal " . date('d M Y', strtotime($dateStr));
    $range = ''; // Kosongkan active range
} else {
    switch ($range) {
    case 'jam':
        $rwhere = "WHERE created_at >= NOW() - INTERVAL 24 HOUR";
        $rgroup = "HOUR(created_at)"; $rtitle = "24 Jam Terakhir"; $chartMode = 'hourly'; break;
    case 'day':
        $rwhere = "WHERE DATE(created_at)=CURDATE()";
        $rgroup = "HOUR(created_at)"; $rtitle = "Hari Ini"; $chartMode = 'hourly'; break;
    case 'week':
        $rwhere = "WHERE created_at >= CURDATE() - INTERVAL 6 DAY";
        $rgroup = "DATE(created_at)"; $rtitle = "Minggu Ini"; break;
    case '6month':
        $rwhere = "WHERE created_at >= CURDATE() - INTERVAL 180 DAY";
        $rgroup = "DATE_FORMAT(created_at,'%Y-%m')"; $rtitle = "6 Bulan"; $chartMode = 'monthly'; break;
        default: // month
            $rwhere = "WHERE created_at >= CURDATE() - INTERVAL 29 DAY";
            $rgroup = "DATE(created_at)"; $rtitle = "30 Hari"; break;
    }
}

try {
    $totalHits    = (int)$pdo->query("SELECT COUNT(*) FROM traffic_logs {$rwhere}")->fetchColumn();
    $uniqueIps    = (int)$pdo->query("SELECT COUNT(DISTINCT ip_address) FROM traffic_logs {$rwhere}")->fetchColumn();

    $orderHits    = (int)$pdo->query("SELECT COUNT(*) FROM traffic_logs {$rwhere} AND action='order_created'")->fetchColumn();
    $convRate     = $totalHits > 0 ? round(($orderHits / $totalHits) * 100, 2) : 0;

    $totalOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders {$rwhere}")->fetchColumn();
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
?>
<style>
.tf-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.range-bar{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.range-btn{padding:5px 14px;border-radius:20px;font-size:12px;font-weight:500;border:1.5px solid var(--c-border);background:transparent;color:var(--c-text-sec);cursor:pointer;transition:.15s;text-decoration:none;display:inline-block}
.range-btn.active,.range-btn:hover{background:#4285F4;border-color:#4285F4;color:#fff}
.metric-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:20px}
.mc{background:var(--c-surface);border:1px solid var(--c-border);border-radius:12px;padding:20px;position:relative;overflow:hidden;transition:.2s}
.mc:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.08)}
.mc__lbl{font-size:11px;font-weight:600;text-transform:uppercase;color:var(--c-text-hint);margin-bottom:8px}
.mc__val{font-size:28px;font-weight:700;line-height:1;color:var(--c-text)}
.mc__sub{font-size:12px;color:var(--c-text-sec);margin-top:6px}
.chart-card{background:var(--c-surface);border:1px solid var(--c-border);border-radius:12px;padding:20px;margin-bottom:20px}
.chart-card__hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:8px}
.chart-card__ttl{font-size:16px;font-weight:600;color:var(--c-text)}
.clg-wrap{display:flex;gap:16px;flex-wrap:wrap;padding-bottom:16px;border-bottom:1px solid var(--c-border);margin-bottom:20px}
.clg-item{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-text-sec);cursor:pointer;user-select:none;transition:.2s}
.clg-item:hover{color:var(--c-text)}
.clg-item input{display:none}
.clg-box{width:16px;height:16px;border-radius:4px;transition:.2s;position:relative;border:2px solid transparent}
.clg-item input:not(:checked) ~ .clg-box{background:transparent !important;border-color:var(--c-border)}
.clg-item input:checked ~ .clg-box::after{content:'';position:absolute;top:2px;left:5px;width:4px;height:7px;border:solid white;border-width:0 2px 2px 0;transform:rotate(45deg)}
</style>

<div class="tf-header">
  <div>
    <h1 class="page-title">Analytics Web</h1>
    <p class="page-sub">Sistem pelacakan trafik, order, dan revenue.</p>
  </div>
</div>

<div class="range-bar" style="margin-bottom:20px;justify-content:space-between">
  <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
    <?php $ranges=['jam'=>'24 Jam','day'=>'Hari Ini','week'=>'Minggu','month'=>'30 Hari','6month'=>'6 Bulan'];
    foreach ($ranges as $k=>$v): ?>
    <a href="?range=<?=$k?>" class="range-btn <?=($range===$k && !$useDate)?'active':''?>"><?=$v?></a>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <form method="GET" style="display:flex;gap:6px;align-items:center;margin:0">
        <input type="date" name="date" value="<?= htmlspecialchars($dateStr) ?>" style="padding:4px 10px;border-radius:20px;border:1.5px solid var(--c-border);font-size:12px;background:var(--c-surface);color:var(--c-text);outline:none">
        <button type="submit" class="range-btn" style="background:#111;color:#fff;border-color:#111">Cari Tgl</button>
    </form>
    <select id="chartTypeToggle" style="padding:5px 12px;border-radius:20px;border:1.5px solid var(--c-border);font-size:12px;background:var(--c-surface);color:var(--c-text);outline:none;cursor:pointer">
        <option value="area">Grafik Gelombang</option>
        <option value="bar">Grafik Batang</option>
        <option value="line">Grafik Garis Smooth</option>
        <option value="stepline">Grafik Tangga</option>
    </select>
  </div>
</div>

<div class="metric-grid">
  <div class="mc">
    <div class="mc__lbl">Total Kunjungan</div><div class="mc__val"><?=number_format($totalHits)?></div>
    <div class="mc__sub">IP Unik: <?=number_format($uniqueIps)?></div>
  </div>
  <div class="mc">
    <div class="mc__lbl">Total Transaksi</div><div class="mc__val"><?=number_format($totalOrders)?></div>
    <div class="mc__sub">Pesanan dibuat (Pending+Success)</div>
  </div>
  <div class="mc">
    <div class="mc__lbl">Transaksi Sukses</div><div class="mc__val"><?=number_format($successOrders)?></div>
    <div class="mc__sub">Conversion: <?=$convRate?>% dari visit</div>
  </div>
  <div class="mc">
    <div class="mc__lbl" style="color:#10b981">Real Revenue</div>
    <div class="mc__val" style="color:#10b981;font-size:24px"><?=fRp($totalRevenue)?></div>
    <div class="mc__sub">Dari transaksi berstatus Success</div>
  </div>
</div>

<div class="chart-card">
  <div class="chart-card__hd" style="margin-bottom:16px">
    <div class="chart-card__ttl">Visualisasi Kinerja Website (<?=htmlspecialchars($rtitle)?>)</div>
  </div>
  
  <div class="clg-wrap" id="chartLegend">
      <label class="clg-item"><input type="checkbox" value="Traffic Hits" checked> <span class="clg-box" style="background:#4285F4"></span> Trafik Hits</label>
      <label class="clg-item"><input type="checkbox" value="Transaksi" checked> <span class="clg-box" style="background:#10b981"></span> Transaksi</label>
      <label class="clg-item"><input type="checkbox" value="Revenue (Rp)" checked> <span class="clg-box" style="background:#f59e0b"></span> Revenue (Rp)</label>
  </div>
  
  <div id="apx-chart" style="min-height:300px"></div>
  <div id="chart-error" style="display:none;padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;font-size:12px;color:#856404;margin-top:8px"></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.49.0/apexcharts.min.js" 
  onerror="document.getElementById('chart-error').style.display='block';document.getElementById('chart-error').innerText='GAGAL LOAD ApexCharts CDN. Cek koneksi internet.'"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
  var errBox = document.getElementById('chart-error');
  
  if (typeof ApexCharts === 'undefined') {
    if(errBox){ errBox.style.display='block'; errBox.innerText = 'ApexCharts gagal di-load dari CDN. Pastikan koneksi internet stabil.'; }
    return;
  }

  try {
    var tRaw = <?= json_encode($trafficChart) ?>;
    var oRaw = <?= json_encode($ordersChart) ?>;
    var mode = <?= json_encode($chartMode) ?>;

    var labels = [], tArr = [], oArr = [], rArr = [];
    
    // Perbaikan mapping hari ini / 24 jam yang datanya bertipe jam (0-23)
    if (mode === 'hourly') {
      for (var i = 0; i < 24; i++) { labels.push((i < 10 ? '0' : '') + i + ':00'); tArr.push(0); oArr.push(0); rArr.push(0); }
      for (var j = 0; j < tRaw.length; j++) { tArr[parseInt(tRaw[j].lbl, 10)] = parseInt(tRaw[j].cnt, 10); }
      for (var k = 0; k < oRaw.length; k++) {
          var idx = parseInt(oRaw[k].lbl, 10);
          oArr[idx] = parseInt(oRaw[k].cnt, 10);
          rArr[idx] = parseFloat(oRaw[k].rev || 0);
      }
    } else {
      var keySet = {};
      for (var a = 0; a < tRaw.length; a++) { keySet[String(tRaw[a].lbl)] = 1; }
      for (var b = 0; b < oRaw.length; b++) { keySet[String(oRaw[b].lbl)] = 1; }
      labels = Object.keys(keySet).sort();
      var tm = {}, om = {}, rm = {};
      for (var c = 0; c < tRaw.length; c++) { tm[String(tRaw[c].lbl)] = parseInt(tRaw[c].cnt, 10); }
      for (var d = 0; d < oRaw.length; d++) {
          om[String(oRaw[d].lbl)] = parseInt(oRaw[d].cnt, 10);
          rm[String(oRaw[d].lbl)] = parseFloat(oRaw[d].rev || 0);
      }
      
      for (var e = 0; e < labels.length; e++) {
        var lb = labels[e];
        tArr.push(tm[lb] || 0);
        oArr.push(om[lb] || 0);
        rArr.push(rm[lb] || 0);
      }
    }

    var savedType = localStorage.getItem('analyticsChartType') || 'area';
    var toggle = document.getElementById('chartTypeToggle');
    if (toggle) toggle.value = savedType;

    var initStyle = (function(t) {
        var isArea = t === 'area';
        var isBar = t === 'bar';
        var isLine = t === 'line';
        var isStep = t === 'stepline';
        return {
            type: isStep ? 'line' : t,
            strokeCurve: isStep ? 'stepline' : (isArea || isLine ? 'smooth' : 'straight'),
            strokeWidth: isBar ? [0,0,0] : [3,3,3],
            fillType: isArea ? 'gradient' : 'solid',
            fillOp: isBar ? 0.9 : 1
        };
    })(savedType);

    var chartOptions = {
      series: [
        { name: 'Traffic Hits', type: initStyle.type, data: tArr },
        { name: 'Transaksi', type: initStyle.type, data: oArr },
        { name: 'Revenue (Rp)', type: initStyle.type, data: rArr }
      ],
      chart: {
        height: 320, type: initStyle.type, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: true, speed: 600 }
      },
      colors: ['#4285F4', '#10b981', '#f59e0b'],
      fill: {
        type: initStyle.fillType,
        gradient: savedType === 'area' ? { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } : undefined,
        opacity: initStyle.fillOp
      },
      dataLabels: { enabled: false },
      stroke: { curve: initStyle.strokeCurve, width: initStyle.strokeWidth },
      markers: { size: 0, hover: { size: 6 } },
      xaxis: {
        categories: labels,
        labels: { style: { colors: '#6b7280', fontSize: '11px' } },
        axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false }
      },
      yaxis: [
        {
          seriesName: 'Traffic Hits',
          title: { text: 'Hits Trafik', style: { color: '#4285F4', fontSize: '11px', fontWeight: 600 } },
          labels: { style: { colors: '#4285F4', fontSize: '11px' } },
          min: 0, forceNiceScale: true
        },
        {
          seriesName: 'Transaksi',
          opposite: true,
          title: { text: 'Transaksi', style: { color: '#10b981', fontSize: '11px', fontWeight: 600 } },
          labels: { style: { colors: '#10b981', fontSize: '11px' } },
          min: 0, forceNiceScale: true
        },
        {
          seriesName: 'Revenue (Rp)',
          opposite: true,
          title: { text: 'Revenue (Rp)', style: { color: '#f59e0b', fontSize: '11px', fontWeight: 600 } },
          labels: { style: { colors: '#f59e0b', fontSize: '11px' }, formatter: function(v){ return v>=1000 ? 'Rp '+(v/1000).toFixed(0)+'k' : 'Rp '+v; } },
          min: 0, forceNiceScale: true
        }
      ],
      grid: { borderColor: 'rgba(128,128,128,0.1)', strokeDashArray: 4 },
      tooltip: { shared: true, intersect: false, theme: 'light', style: { fontSize: '12px' }, y: { formatter: function(val, { seriesIndex }) { return seriesIndex === 2 ? 'Rp ' + val.toLocaleString() : val; } } },
      legend: { show: false }
    };

    var chartEl = document.getElementById('apx-chart');
    if (chartEl) {
      var chart = new ApexCharts(chartEl, chartOptions);
      chart.render();
      
      if (toggle) {
          toggle.addEventListener('change', function() {
              var t = this.value;
              localStorage.setItem('analyticsChartType', t);
              
              var isArea = t === 'area';
              var isBar = t === 'bar';
              var isLine = t === 'line';
              var isStep = t === 'stepline';
              var actualType = isStep ? 'line' : t;
              
              chart.updateOptions({
                  chart: { type: actualType },
                  stroke: { curve: isStep ? 'stepline' : (isArea || isLine ? 'smooth' : 'straight'), width: isBar ? [0,0,0] : [3,3,3] },
                  fill: {
                      type: isArea ? 'gradient' : 'solid',
                      gradient: isArea ? { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0,100] } : undefined,
                      opacity: isBar ? 0.9 : 1
                  }
              });
              
              chart.updateSeries([
                  { name: 'Traffic Hits', type: actualType, data: tArr },
                  { name: 'Transaksi', type: actualType, data: oArr },
                  { name: 'Revenue (Rp)', type: actualType, data: rArr }
              ]);
          });
      }
      
      // Custom Legend Checkbox Toggle
      var legendChecks = document.querySelectorAll('#chartLegend input[type="checkbox"]');
      legendChecks.forEach(function(chk) {
          chk.addEventListener('change', function() {
              chart.toggleSeries(this.value);
          });
      });
    }
  } catch (ex) {
    if(errBox){ errBox.style.display='block'; errBox.innerText = 'JS Error: ' + ex.message; }
    console.error(ex);
  }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
