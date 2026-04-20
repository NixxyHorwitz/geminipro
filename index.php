<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
use App\Config;

$headerTitle = Config::get('product_name', 'Google AI Pro');
$siteTitle   = Config::get('site_title', $headerTitle);
$siteTagline = Config::get('site_tagline', 'Paket Lengkap 12 Bulan');
$footerText  = Config::get('footer_text', 'Bukan afiliasi resmi Google LLC.');
$favicon     = Config::get('favicon_file', '');

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
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
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
    // SVG icons as Material Design paths
    $svgGemini   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>';
    $svgVideo    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"/></svg>';
    $svgImage    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>';
    $svgResearch = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>';
    $svgCloud    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/></svg>';
    $svgCredits  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    $svgNotebook = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>';
    $svgMail     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>';
    $svgMusic    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/></svg>';
    $svgCode     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>';
    $svgAndroid  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>';

    $features = [
      [$svgGemini,   'Gemini 3.1 Pro',      'Model AI paling canggih dari Google. Deep Research, percakapan multi-modal, dan analisis mendalam.','#e8f0fe','#1a73e8'],
      [$svgVideo,    'Veo 3.1 Video AI',    'Buat video sinematik berkualitas tinggi dari teks atau gambar dengan teknologi generasi terbaru.','#fce8e6','#ea4335'],
      [$svgImage,    'Image Generation',    'Nano Banana Pro — buat gambar fotorealistis dan artwork dalam hitungan detik.','#e6f4ea','#34a853'],
      [$svgResearch, 'Deep Research',       'Riset mendalam otomatis dengan sumber valid. Hemat berjam-jam pekerjaan riset manual.','#fff8e1','#f29900'],
      [$svgCloud,    '5 TB Storage',        'Penyimpanan total untuk Google Foto, Drive, dan Gmail. Tidak perlu khawatir kehabisan.','#fce8e6','#ea4335'],
      [$svgCredits,  '1.000 AI Credits/bln','Kredit AI untuk akses lebih tinggi ke semua fitur premium Google AI.','#e8f0fe','#1a73e8'],
      [$svgNotebook, 'NotebookLM Plus',     'Partner riset cerdas dengan Ringkasan Audio & Video, Kuis, dan analisis dokumen.','#e6f4ea','#34a853'],
      [$svgMail,     'Gemini di Gmail',     'Drafting email cerdas, ringkasan thread panjang, dan balasan otomatis langsung di Gmail.','#fff8e1','#f29900'],
      [$svgMusic,    'Producer.ai',         'Platform pembuatan musik kolaboratif berbasis AI. Compose, produce, dan publish.','#fce8e6','#ea4335'],
      [$svgCode,     'Google Antigravity',  'Batas tarif lebih tinggi untuk model agen agentic — sempurna untuk developer.','#e8f0fe','#1a73e8'],
      [$svgCode,     'Developer Program',   'Batas lebih tinggi untuk Gemini CLI, Code Assist, Jules, dan kredit Cloud.','#e6f4ea','#34a853'],
      [$svgAndroid,  'Android Studio AI',   'Optimalkan pengembangan Android dengan Gemini terbaik langsung di IDE Anda.','#fff8e1','#f29900'],
    ];
    foreach ($features as [$icon, $title, $desc, $bg, $color]):
    ?>
    <div class="feature-card">
      <div class="feature-card__icon" style="background:<?= $bg ?>;color:<?= $color ?>">
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
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>
        Paket 12 bulan — hemat vs beli sendiri
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
  <p style="margin-top:6px"><a href="#">Syarat & Ketentuan</a> · <a href="#">Kebijakan Privasi</a> �</p>
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
