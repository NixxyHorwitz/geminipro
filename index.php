<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

use App\Config;
use App\Logger;
use App\TelegramBot;

// Redirect to setup if not configured
if (!Config::isSetupComplete()) {
    header('Location: /setup.php'); exit;
}

// Log visit
if ($pdo) {
    $bot    = new TelegramBot(Config::get('telegram_bot_token', ''));
    $admin  = (int) Config::get('telegram_admin_chat_id', 0);
    $logger = new Logger($pdo, $bot, $admin);
    $logger->log('/', 'page_view');

    // Only notify for new session visits
    if (empty($_SESSION['visited'])) {
        $_SESSION['visited'] = true;
        $logger->notifyTraffic('new_visit', [
            'Referer' => $_SERVER['HTTP_REFERER'] ?? 'Direct',
            'UA'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 60),
        ]);
    }
}

$price    = (int) Config::get('product_price', 309000);
$priceStr = 'Rp ' . number_format($price, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Google AI Pro — Paket Lengkap 12 Bulan</title>
<meta name="description" content="Dapatkan akses penuh ke Google AI Pro selama 12 bulan. Gemini, Veo, Deep Research, 5TB Storage, dan banyak lagi. Hanya <?= $priceStr ?>/bulan.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="assets/css/main.css">
</head>
<body>

<!-- HEADER -->
<header class="header">
  <a href="/" class="header__logo">
    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M28 16.3C28 9.5 22.6 4 16 4C9.4 4 4 9.5 4 16C4 22.5 9.4 28 16 28H28V16.3Z" fill="#4285F4"/>
      <path d="M22 16H16V22H22V16Z" fill="white"/>
      <path d="M16 10H10V16H16V10Z" fill="white"/>
      <path d="M22 10H16V16H22V10Z" fill="#FBBC04"/>
    </svg>
    Google AI Pro
  </a>
  <nav class="header__nav">
    <a href="#features" class="btn btn--ghost btn--sm">Fitur</a>
    <a href="#pricing" class="btn btn--primary btn--sm" id="header-buy-btn">Beli Sekarang</a>
  </nav>
</header>

<!-- HERO -->
<section class="hero">
  <div class="hero__eyebrow fade-up">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    Reseller Resmi · Bergaransi
  </div>
  <h1 class="hero__title fade-up fade-up--1">
    Akses Penuh <span>Google AI Pro</span><br>untuk Kreativitas Tanpa Batas
  </h1>
  <p class="hero__subtitle fade-up fade-up--2">
    Gemini 3.1 Pro · Deep Research · Veo 3.1 · 5 TB Storage · 1.000 AI Credits/bulan dan masih banyak lagi — semua dalam satu paket.
  </p>
  <div class="fade-up fade-up--3" style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <a href="#pricing" class="btn btn--primary btn--lg" id="hero-cta">Mulai Sekarang — <?= $priceStr ?></a>
    <a href="#features" class="btn btn--ghost btn--lg">Lihat Semua Fitur</a>
  </div>
</section>

<!-- TRUST BAR -->
<div class="trust-bar fade-up fade-up--4">
  <div class="trust-item">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    Aktivasi Instan
  </div>
  <div class="trust-item">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    Pembayaran QRIS Aman
  </div>
  <div class="trust-item">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    Dukungan 24/7 via Telegram
  </div>
  <div class="trust-item">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    Garansi Uang Kembali
  </div>
</div>

<!-- FEATURES SECTION -->
<section class="features-section" id="features">
  <h2>Semua yang Anda Butuhkan</h2>
  <p class="subtitle">Satu langganan, akses ke seluruh ekosistem AI Google</p>
  <div class="features-grid">
    <?php
    $features = [
      ['🤖','Gemini 3.1 Pro','Model AI paling canggih dari Google. Deep Research, percakapan multi-modal, dan analisis mendalam.','#e8f0fe'],
      ['🎬','Veo 3.1 Video AI','Buat video sinematik berkualitas tinggi dari teks atau gambar dengan teknologi generasi terbaru.','#fce8e6'],
      ['🎨','Image Generation','Nano Banana Pro — buat gambar fotorealistis dan artwork dalam hitungan detik.','#e6f4ea'],
      ['🔬','Deep Research','Riset mendalam otomatis dengan sumber valid. Hemat berjam-jam pekerjaan riset manual.','#fff8e1'],
      ['💾','5 TB Storage','Penyimpanan total untuk Google Foto, Drive, dan Gmail. Tidak perlu khawatir kehabisan.','#fce8e6'],
      ['✨','1.000 AI Credits/bln','Kredit AI untuk akses lebih tinggi ke semua fitur premium Google AI.','#e8f0fe'],
      ['📓','NotebookLM Plus','Partner riset cerdas dengan Ringkasan Audio & Video, Kuis, dan analisis dokumen.','#e6f4ea'],
      ['📧','Gemini di Gmail','Drafting email cerdas, ringkasan thread panjang, dan balasan otomatis langsung di Gmail.','#fff8e1'],
      ['🎵','Producer.ai','Platform pembuatan musik kolaboratif berbasis AI. Compose, produce, dan publish.','#fce8e6'],
      ['💻','Google Antigravity','Batas tarif lebih tinggi untuk model agen agentic — sempurna untuk developer.','#e8f0fe'],
      ['🤝','Developer Program','Batas lebih tinggi untuk Gemini CLI, Code Assist, Jules, dan kredit Cloud.','#e6f4ea'],
      ['📱','Android Studio AI','Optimalkan pengembangan Android dengan Gemini terbaik langsung di IDE Anda.','#fff8e1'],
    ];
    foreach ($features as [$icon, $title, $desc, $bg]):
    ?>
    <div class="feature-card">
      <div class="feature-card__icon" style="background:<?= $bg ?>">
        <?= $icon ?>
      </div>
      <div class="feature-card__title"><?= $title ?></div>
      <div class="feature-card__desc"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- PRICING -->
<section class="pricing-section" id="pricing">
  <div class="pricing-card fade-up">
    <div class="pricing-card__badge">Terlaris</div>
    <div class="pricing-card__header">
      <div class="pricing-card__name">Google AI Pro</div>
      <div class="pricing-card__tagline">Dapatkan akses lebih tinggi ke fitur baru dan canggih</div>
      <div class="pricing-card__price">
        <div class="pricing-card__amount"><?= $priceStr ?></div>
        <div class="pricing-card__period">/bln</div>
      </div>
      <div class="pricing-card__promo">
        🎁 Paket 12 bulan — hemat vs beli sendiri
      </div>
    </div>
    <div class="pricing-card__body">
      <div class="pricing-card__cta">
        <a href="checkout.php" class="btn btn--primary btn--full btn--lg" id="pricing-buy-btn">
          Beli Sekarang
        </a>
      </div>
      <ul class="feature-list">
        <li><strong>Gemini App</strong> — Akses lebih tinggi ke Gemini 3.1 Pro + Deep Research</li>
        <li><strong>Veo 3.1</strong> — Pembuatan video AI sinematik (teks → video)</li>
        <li><strong>Whisk</strong> — Video dari gambar dengan Veo 3</li>
        <li><strong>Nano Banana Pro</strong> — Image generation premium</li>
        <li><strong>1.000 AI Credits</strong> per bulan</li>
        <li><strong>NotebookLM Plus</strong> — Riset dengan ringkasan audio & video</li>
        <li><strong>Gemini di Gmail, Docs, Vids</strong> — AI langsung di Google Workspace</li>
        <li><strong>Google Penelusuran</strong> — Didukung Gemini 3 Pro</li>
        <li><strong>Producer.ai</strong> — Platform musik kolaboratif berbasis AI</li>
        <li><strong>Google Antigravity</strong> — Batas lebih tinggi untuk model agentic</li>
        <li><strong>Developer Program Premium</strong> — Gemini CLI, Jules, Cloud credits</li>
        <li><strong>Gemini di Android Studio</strong></li>
        <li><strong>5 TB Storage</strong> — Foto, Drive, Gmail</li>
      </ul>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section style="padding:64px 24px;max-width:800px;margin:0 auto">
  <h2 style="text-align:center;margin-bottom:8px">Cara Kerja</h2>
  <p style="text-align:center;color:var(--c-text-sec);font-size:16px;margin-bottom:48px">Proses mudah, aktivasi cepat</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:32px;text-align:center">
    <?php
    $steps = [
      ['1','Pilih Metode','Pilih aktivasi SSO (Google login) atau link aktivasi via email'],
      ['2','Bayar QRIS','Scan QR Code, bayar lewat aplikasi e-wallet Anda'],
      ['3','Konfirmasi','Admin mengkonfirmasi pembayaran Anda via Telegram'],
      ['4','Aktifasi','Terima link aktivasi dan nikmati semua fitur Google AI Pro'],
    ];
    foreach ($steps as [$n, $t, $d]):
    ?>
    <div>
      <div style="width:52px;height:52px;border-radius:50%;background:var(--c-blue);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 16px">
        <?= $n ?>
      </div>
      <div style="font-weight:600;margin-bottom:6px"><?= $t ?></div>
      <div style="font-size:13px;color:var(--c-text-sec)"><?= $d ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- FAQ -->
<section style="padding:0 24px 80px;max-width:720px;margin:0 auto">
  <h2 style="text-align:center;margin-bottom:32px">FAQ</h2>
  <div id="faq-list"></div>
  <?php
  $faqs = [
    ['Apakah ini resmi dari Google?', 'Ini adalah layanan reseller berlisensi. Setelah pembayaran dikonfirmasi, Anda akan mendapat link aktivasi Google AI Pro asli.'],
    ['Berapa lama proses aktivasi?', 'Biasanya dalam 1-5 menit setelah pembayaran dikonfirmasi admin kami.'],
    ['Metode pembayaran apa yang diterima?', 'Saat ini hanya QRIS — bisa dibayar lewat GoPay, OVO, Dana, QRIS BCA, Shopeepay, dan semua dompet digital yang mendukung QRIS.'],
    ['Apakah ada garansi?', 'Ya, jika ada masalah dengan aktivasi dalam 7 hari pertama, kami akan refund penuh.'],
    ['Apa yang dimaksud metode SSO vs Link Aktivasi?', 'SSO: Anda login dengan akun Google Anda. Link Aktivasi: Admin mengirimi Anda link invite ke email tujuan.'],
  ];
  foreach ($faqs as [$q, $a]):
  ?>
  <details style="border:1px solid var(--c-border);border-radius:var(--radius-md);margin-bottom:8px;overflow:hidden;cursor:pointer">
    <summary style="padding:16px 20px;font-size:14px;font-weight:500;display:flex;justify-content:space-between;align-items:center;user-select:none">
      <?= $q ?>
      <span style="font-size:18px;color:var(--c-text-sec);flex-shrink:0">+</span>
    </summary>
    <div style="padding:0 20px 16px;font-size:14px;color:var(--c-text-sec)"><?= $a ?></div>
  </details>
  <?php endforeach; ?>
</section>

<!-- FOOTER -->
<footer class="footer">
  <p>© <?= date('Y') ?> Google AI Pro Reseller. Bukan afiliasi resmi Google LLC.</p>
  <p style="margin-top:6px"><a href="#">Syarat & Ketentuan</a> · <a href="#">Kebijakan Privasi</a> · <a href="https://t.me/" target="_blank">Hubungi Admin</a></p>
</footer>

<script>
// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) { e.preventDefault(); target.scrollIntoView({behavior:'smooth', block:'start'}); }
  });
});

// Intersection Observer for fade-up
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); } });
}, { threshold: .1 });
document.querySelectorAll('.feature-card,.fade-up').forEach(el => observer.observe(el));

// details toggle icon
document.querySelectorAll('details').forEach(d => {
  d.addEventListener('toggle', () => {
    d.querySelector('span').textContent = d.open ? '−' : '+';
  });
});
</script>
</body>
</html>
