<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Config;
use App\Order;

$order = new Order($pdo);

// Stats
$price = (int) Config::get('product_price', 309000);

// Quick stats queries
try {
    $todayVisits = (int) $pdo->query("SELECT COUNT(*) FROM traffic_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $todayOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $pendingCnt  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
    $confirmedCnt= (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='confirmed'")->fetchColumn();
    $totalRev    = (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='confirmed'")->fetchColumn();
    $todayRev    = (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='confirmed' AND DATE(confirmed_at)=CURDATE()")->fetchColumn();

    // Chart data last 7 days
    $chartData = $pdo->query(
        "SELECT DATE(created_at) as d, COUNT(*) as cnt FROM orders 
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
         GROUP BY DATE(created_at) ORDER BY d"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Revenue last 7 days
    $revenueData = $pdo->query(
        "SELECT DATE(confirmed_at) as d, COALESCE(SUM(amount),0) as rev FROM orders 
         WHERE status='confirmed' AND confirmed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
         GROUP BY DATE(confirmed_at) ORDER BY d"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Recent orders
    $recentOrders = $pdo->query(
        "SELECT * FROM orders ORDER BY created_at DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Traffic action breakdown
    $actions = $pdo->query(
        "SELECT action, COUNT(*) as cnt FROM traffic_logs WHERE DATE(created_at)=CURDATE() GROUP BY action ORDER BY cnt DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch(\Exception $e) {
    $todayVisits = $todayOrders = $pendingCnt = $confirmedCnt = $totalRev = $todayRev = 0;
    $recentOrders = $chartData = $revenueData = $actions = [];
}

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-sub">Ringkasan aktivitas Google AI Pro hari ini</p>
  </div>
  <div class="page-header__actions">
    <span class="badge badge--info"><?= date('d M Y') ?></span>
  </div>
</div>

<!-- Stat cards -->
<div class="stat-grid">
  <div class="stat-card stat-card--blue">
    <div class="stat-card__icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
    </div>
    <div class="stat-card__body">
      <div class="stat-card__label">Pengunjung Hari Ini</div>
      <div class="stat-card__value"><?= number_format($todayVisits) ?></div>
    </div>
  </div>
  <div class="stat-card stat-card--yellow">
    <div class="stat-card__icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
    </div>
    <div class="stat-card__body">
      <div class="stat-card__label">Order Pending</div>
      <div class="stat-card__value"><?= $pendingCnt ?></div>
      <?php if ($pendingCnt > 0): ?>
      <a href="/console/orders.php?status=pending" class="stat-card__action">Lihat sekarang →</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="stat-card stat-card--green">
    <div class="stat-card__icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
    </div>
    <div class="stat-card__body">
      <div class="stat-card__label">Revenue Hari Ini</div>
      <div class="stat-card__value"><?= Order::formatRp($todayRev) ?></div>
    </div>
  </div>
  <div class="stat-card stat-card--purple">
    <div class="stat-card__icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
    </div>
    <div class="stat-card__body">
      <div class="stat-card__label">Total Revenue</div>
      <div class="stat-card__value"><?= Order::formatRp($totalRev) ?></div>
      <div class="stat-card__sub"><?= $confirmedCnt ?> order confirmed</div>
    </div>
  </div>
</div>

<!-- Charts + recent orders -->
<div class="dashboard-grid">
  <!-- Orders Chart -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Order (7 Hari Terakhir)</div>
    </div>
    <div class="card__body">
      <canvas id="ordersChart" height="200"></canvas>
    </div>
  </div>

  <!-- Recent orders -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Order Terbaru</div>
      <a href="/console/orders.php" class="card__link">Lihat semua →</a>
    </div>
    <div class="card__body" style="padding:0">
      <?php if (empty($recentOrders)): ?>
        <div style="padding:24px;text-align:center;color:var(--c-text-hint)">Belum ada order</div>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Kode</th><th>Email</th><th>Status</th><th>Nominal</th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><code><?= htmlspecialchars($o['order_code']) ?></code></td>
          <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($o['email']) ?></td>
          <td><span class="badge badge--<?= match($o['status']) { 'confirmed'=>'success', 'pending'=>'warn', 'rejected'=>'error', default=>'neutral' } ?>"><?= ucfirst($o['status']) ?></span></td>
          <td><?= Order::formatRp((int)$o['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartData = <?= json_encode($chartData) ?>;

// Build labels for 7 days
const labels = [];
const ordersArr = [];
for (let i=6; i>=0; i--) {
  const d = new Date(); d.setDate(d.getDate()-i);
  const key = d.toISOString().slice(0,10);
  labels.push(d.toLocaleDateString('id-ID',{day:'numeric',month:'short'}));
  const found = chartData.find(r => r.d === key);
  ordersArr.push(found ? parseInt(found.cnt) : 0);
}

new Chart(document.getElementById('ordersChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Jumlah Order',
      data: ordersArr,
      backgroundColor: 'rgba(66,133,244,0.15)',
      borderColor: '#4285F4',
      borderWidth: 2,
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } }
  }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
