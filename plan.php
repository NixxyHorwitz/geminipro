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
<title><?= htmlspecialchars($siteTitle) ?><?= $siteTagline ? ' — ' . htmlspecialchars($siteTagline) : '' ?></title>
<meta name="description" content="<?= htmlspecialchars($siteTitle) ?> — <?= htmlspecialchars($siteTagline) ?>. Hanya <?= $priceStr ?>.">
<?php if ($favicon): ?>
<link rel="icon" href="/assets/img/<?= htmlspecialchars($favicon) ?>?v=<?= time() ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="assets/css/main.css?v=<?= time() ?>">
</head>
<body>

<!-- ANNOUNCEMENT BAR -->
<div class="announce-bar">
  <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M11 5.882V16.24a1.5 1.5 0 01-2.895.37l-.014-.37V5.882c0-.828.672-1.5 1.5-1.5s1.5.672 1.5 1.5zM13 16.24V5.882a1.5 1.5 0 013 0V16.24a1.5 1.5 0 01-3 0z" fill="none"/><path d="M12 2a1 1 0 01.993.883L13 3v1.586l3.707-3.707a1 1 0 011.497 1.32l-.083.094L13 7.414V9.17l4.604-4.604a1 1 0 011.497 1.32l-.083.094L13 11.586V16a5 5 0 01-9.995.217L3 16V8a1 1 0 011.993-.117L5 8v8a3 3 0 005.995.176L11 16V3a1 1 0 011-1z" fill="none"/><path d="M21 11.5a1 1 0 110 2 1 1 0 010-2zm-18 0a1 1 0 110 2 1 1 0 010-2zM11.5 2a1 1 0 110 2 1 1 0 010-2zm1 0a1 1 0 110 2 1 1 0 010-2z" fill="none"/><path d="M12 2C9.243 2 7 4.243 7 7v4l-1.293 1.293A1 1 0 005 13v3a1 1 0 001 1h1.341A4.502 4.502 0 0012 21.5a4.502 4.502 0 004.659-4.5H18a1 1 0 001-1v-3a1 1 0 00-.707-.707L17 11V7c0-2.757-2.243-5-5-5z" fill="none"/><path d="M12 1.5c-1.933 0-3.5 1.567-3.5 3.5s1.567 3.5 3.5 3.5 3.5-1.567 3.5-3.5S13.933 1.5 12 1.5z" fill="none"/><path d="M19.071 4.929a1 1 0 010 1.414L13.414 12l5.657 5.657a1 1 0 01-1.414 1.414L12 13.414l-5.657 5.657a1 1 0 01-1.414-1.414L10.586 12 4.929 6.343a1 1 0 011.414-1.414L12 10.586l5.657-5.657a1 1 0 011.414 0z" fill="none"/>
<path d="M12 2c.55 0 .99.44.99.99v.5c2.32.41 4 2.41 4 4.79v.51l.38.27A2 2 0 0118 10.62V14c0 1.11-.89 2-2 2H8c-1.11 0-2-.89-2-2v-3.38A2 2 0 016.62 9.07L7 8.79v-.51C7 5.9 8.68 3.9 11 3.49v-.5c0-.55.45-.99 1-.99zm0 3c-1.66 0-3 1.34-3 3v1h6V8c0-1.66-1.34-3-3-3z"/></svg>
  Promo Terbatas — Paket 12 Bulan Google AI Pro.&nbsp;<a href="#pricing">Beli Sekarang →</a>
</div>

<!-- HEADER -->
<header class="header">
  <a href="/plan" class="header__logo">
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
    Akses Penuh <span class="google-text"><span style="color:#4285F4">G</span><span style="color:#EA4335">o</span><span style="color:#FBBC04">o</span><span style="color:#4285F4">g</span><span style="color:#34A853">l</span><span style="color:#EA4335">e</span></span> <span class="hero__title-pro">AI Pro</span><br>
    <span style="font-weight:400;color:#5f6368;font-size:.72em">untuk Kreativitas Tanpa Batas</span>
  </h1>
  <p class="hero__subtitle fade-up fade-up--2">
    Gemini 3.1 Pro · Deep Research · Veo 3.1 · 5 TB Storage · 1.000 AI Credits/bulan — semua dalam satu paket.
  </p>
  <div class="hero__actions fade-up fade-up--3">
    <a href="#pricing" class="btn btn--primary btn--lg" id="hero-cta">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>
      Beli Sekarang &mdash; <?= $priceStr ?>
    </a>
    <a href="#how-it-works" class="btn btn--ghost btn--lg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
      Cara Kerja
    </a>
  </div>

  <!-- Rating + trust signals -->
  <div class="hero__trust fade-up fade-up--4">
    <div class="hero__rating">
      <div class="hero__stars">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#FBBC04"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#FBBC04"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#FBBC04"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#FBBC04"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#FBBC04"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
      </div>
      <span><strong>4.9/5</strong> dari 1.200+ pengguna aktif</span>
    </div>
    <div class="hero__badges">
      <div class="hero-badge">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.07-.31.18-.62.18-.97C18 3.35 16.65 2 15.03 2c-.98 0-1.84.49-2.35 1.22L12 4.21l-.68-.99C10.81 2.49 9.95 2 8.97 2 7.35 2 6 3.35 6 4.97c0 .36.11.67.18.97H4c-1.11 0-2 .89-2 2v1c0 .57.43 1 1 1h18c.57 0 1-.43 1-1V8c0-1.11-.89-2-2-2zm-5-.03c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zM9 5.03c0-.55.45-1 1-1s1 .45 1 1-.45 1-1 1-1-.45-1-1zM4 11v8c0 1.11.89 2 2 2h12c1.11 0 2-.89 2-2v-8H4zm6 7H6v-5h4v5zm8 0h-4v-5h4v5z"/></svg>
        Dikirim via <strong>Google Gift Offer</strong>
      </div>
      <div class="hero-badge hero-badge--green">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
        Akun Pribadi — Bukan shared
      </div>
    </div>
  </div>
</section>

<!-- TRUST BAR -->
<div class="trust-bar">
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
    Support 24/7 via Telegram
  </div>
  <div class="trust-item">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    Garansi Uang Kembali 7 Hari
  </div>
</div>

<!-- STATS BAR -->
<div class="stats-bar">
  <div class="stats-bar__inner">
    <div class="stat-item"><div class="stat-item__num"><span>1.200</span>+</div><div class="stat-item__label">Pengguna Aktif</div></div>
    <div class="stat-item"><div class="stat-item__num"><span>4.9</span>/5</div><div class="stat-item__label">Rating Kepuasan</div></div>
    <div class="stat-item"><div class="stat-item__num">&lt;<span>5</span> mnt</div><div class="stat-item__label">Rata-rata Aktivasi</div></div>
    <div class="stat-item"><div class="stat-item__num"><span>100</span>%</div><div class="stat-item__label">Garansi Uang Kembali</div></div>
  </div>
</div>

<!-- FEATURES -->
<section class="features-section" id="features">
  <h2>Semua yang Anda Butuhkan</h2>
  <p class="subtitle">Satu langganan, akses ke seluruh ekosistem AI Google</p>
  <div class="features-grid">
    <?php
    $svgGemini     = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C10.84 7.47 7.47 10.84 2 12c5.47 1.16 8.84 4.53 10 10 1.16-5.47 4.53-8.84 10-10C16.53 10.84 13.16 7.47 12 2z"/></svg>';
    $svgVideo      = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>';
    $svgImage      = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>';
    $svgResearch   = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>';
    $svgCloud      = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg>';
    $svgCredits    = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 2v11h3v9l7-12h-4l4-8z"/></svg>';
    $svgNotebook   = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 4h2v5l-1-.75L9 9V4zm9 16H6V4h1v9l3-2.25L13 13V4h5v16z"/></svg>';
    $svgMail       = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>';
    $svgMusic      = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>';
    $svgCode       = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>';
    $svgDev        = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z"/></svg>';
    $svgAndroid    = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 18c0 .55.45 1 1 1h1v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h2v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h1c.55 0 1-.45 1-1V8H6v10zm-2.5-2C2.67 16 2 15.33 2 14.5v-5C2 8.67 2.67 8 3.5 8S5 8.67 5 9.5v5c0 .83-.67 1.5-1.5 1.5zm17 0c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5zM12 1.5C9.33 1.5 7.02 2.89 5.72 5h12.56C16.98 2.89 14.67 1.5 12 1.5zM9.5 4c-.28 0-.5-.22-.5-.5s.22-.5.5-.5.5.22.5.5-.22.5-.5.5zm5 0c-.28 0-.5-.22-.5-.5s.22-.5.5-.5.5.22.5.5-.22.5-.5.5z"/></svg>';
    $svgClaude     = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L8.5 8.5 2 12l6.5 3.5L12 22l3.5-6.5L22 12l-6.5-3.5L12 2zm0 3.8l2.4 4.5 4.8 2.7-4.8 2.7L12 20.2l-2.4-4.5L4.8 13l4.8-2.7L12 5.8z"/></svg>';
    $svgCloudAI    = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>';
    $svgClaudeChat = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 9l-1 2-2 1 2 1 1 2 1-2 2-1-2-1-1-2z"/></svg>';

    $features = [
      [$svgGemini,     'Gemini 3.1 Pro',         'Model AI paling canggih dari Google. Deep Research, percakapan multi-modal, dan analisis mendalam.', '#e8f0fe','#1a73e8'],
      [$svgVideo,      'Veo 3.1 Video AI',        'Buat video sinematik berkualitas tinggi dari teks atau gambar dengan teknologi generasi terbaru.',   '#fce8e6','#ea4335'],
      [$svgImage,      'Image Generation',        'Nano Banana Pro — buat gambar fotorealistis dan artwork dalam hitungan detik.',                      '#e6f4ea','#34a853'],
      [$svgResearch,   'Deep Research',           'Riset mendalam otomatis dengan sumber valid. Hemat berjam-jam pekerjaan riset manual.',               '#fff8e1','#f29900'],
      [$svgCloud,      '5 TB Storage',            'Penyimpanan total untuk Google Foto, Drive, dan Gmail. Tidak perlu khawatir kehabisan.',              '#fce8e6','#ea4335'],
      [$svgCredits,    '1.000 AI Credits/bln',    'Kredit AI untuk akses lebih tinggi ke semua fitur premium Google AI.',                               '#e8f0fe','#1a73e8'],
      [$svgNotebook,   'NotebookLM Plus',         'Partner riset cerdas dengan Ringkasan Audio & Video, Kuis, dan analisis dokumen.',                   '#e6f4ea','#34a853'],
      [$svgMail,       'Gemini di Gmail',         'Drafting email cerdas, ringkasan thread panjang, dan balasan otomatis langsung di Gmail.',            '#fff8e1','#f29900'],
      [$svgMusic,      'Producer.ai',             'Platform pembuatan musik kolaboratif berbasis AI. Compose, produce, dan publish.',                    '#fce8e6','#ea4335'],
      [$svgCode,       'Google Antigravity',      'Batas tarif lebih tinggi untuk model agen agentic — sempurna untuk developer.',                      '#e8f0fe','#1a73e8'],
      [$svgDev,        'Developer Program',       'Batas lebih tinggi untuk Gemini CLI, Code Assist, Jules, dan kredit Cloud.',                         '#e6f4ea','#34a853'],
      [$svgAndroid,    'Android Studio AI',       'Optimalkan pengembangan Android dengan Gemini terbaik langsung di IDE Anda.',                        '#fff8e1','#f29900'],
      [$svgClaude,     'Claude Sonnet & Opus',    'Akses model Claude terbaru dari Anthropic — penalaran kompleks, coding canggih, analisis mendalam.', '#f3f0ff','#7c3aed'],
      [$svgClaudeChat, 'Claude di Workspace',     'Claude terintegrasi di Google Docs, Gmail, dan Sheets — bantu menulis, edit, merangkum konten.',     '#ede9fe','#6d28d9'],
      [$svgCloudAI,    'Claude via Antigravity',  'Jalankan Claude Haiku, Sonnet, dan Opus via Antigravity — skalabel, aman, enterprise-grade.',        '#f5f3ff','#5b21b6'],
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

<!-- SECURITY BADGES -->
<div class="security-section">
  <div class="security-section__inner">
    <div class="sec-badge">
      <div class="sec-badge__icon" style="background:#e8f0fe;color:#1a73e8">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm0 4l4 2v4c0 3.1-2.12 5.99-4 6.82-1.88-.83-4-3.72-4-6.82V8l4-2z"/></svg>
      </div>
      <div><div class="sec-badge__title">Pembayaran Aman</div><div class="sec-badge__sub">Diproses via QRIS resmi BI</div></div>
    </div>
    <div class="sec-badge">
      <div class="sec-badge__icon" style="background:#e6f4ea;color:#34a853">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
      </div>
      <div><div class="sec-badge__title">Reseller Terverifikasi</div><div class="sec-badge__sub">Link aktivasi asli dari Google</div></div>
    </div>
    <div class="sec-badge">
      <div class="sec-badge__icon" style="background:#f3f0ff;color:#7c3aed">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
      </div>
      <div><div class="sec-badge__title">Support 24/7</div><div class="sec-badge__sub">Respons cepat via Telegram</div></div>
    </div>
    <div class="sec-badge">
      <div class="sec-badge__icon" style="background:#fff8e1;color:#f29900">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1C5.93 1 1 5.93 1 12s4.93 11 11 11 11-4.93 11-11S18.07 1 12 1zm1 15.5h-2v-2h2v2zm0-4h-2v-6h2v6z"/></svg>
      </div>
      <div><div class="sec-badge__title">Garansi 7 Hari</div><div class="sec-badge__sub">Refund penuh jika gagal aktif</div></div>
    </div>
  </div>
</div>

<!-- PRICING -->
<section class="pricing-section" id="pricing">
  <div class="pricing-card fade-up">
    <div class="pricing-card__badge">
      <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
      Terlaris
    </div>
    <div class="pricing-card__header">
      <div style="margin-bottom:10px">
        <span class="urgency-badge">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67z"/></svg>
          Hanya tersisa beberapa slot hari ini
        </span>
      </div>
      <div class="pricing-card__name">Google AI Pro</div>
      <div class="pricing-card__tagline">Dapatkan akses lebih tinggi ke fitur baru dan canggih</div>
      <div class="pricing-card__price">
        <div class="pricing-card__amount"><?= $priceStr ?></div>
        <div class="pricing-card__period">/12 bulan</div>
      </div>
      <div class="pricing-card__promo">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>
        Paket 12 bulan — hemat vs beli sendiri
      </div>
    </div>
    <div class="pricing-card__body">
      <div class="pricing-card__cta">
        <a href="/checkout" class="btn btn--primary btn--full btn--lg" id="pricing-buy-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 12.998h-5v5h-2v-5H6v-2h5v-5h2v5h5z"/></svg>
          Beli Sekarang
        </a>
      </div>
      <ul class="feature-list">
        <li><strong>Gemini App</strong> — Akses lebih tinggi ke Gemini 3.1 Pro + Deep Research</li>
        <li><strong>Veo 3.1</strong> — Pembuatan video AI sinematik (teks → video)</li>
        <li><strong>Whisk</strong> — Video dari gambar dengan Veo 3</li>
        <li><strong>Nano Banana Pro</strong> — Image generation premium</li>
        <li><strong>1.000 AI Credits</strong> per bulan</li>
        <li><strong>NotebookLM Plus</strong> — Riset dengan ringkasan audio &amp; video</li>
        <li><strong>Gemini di Gmail, Docs, Vids</strong> — AI langsung di Google Workspace</li>
        <li><strong>Google Penelusuran</strong> — Didukung Gemini 3 Pro</li>
        <li><strong>Producer.ai</strong> — Platform musik kolaboratif berbasis AI</li>
        <li><strong>Google Antigravity</strong> — Batas lebih tinggi untuk model agentic</li>
        <li><strong>Developer Program Premium</strong> — Gemini CLI, Jules, Cloud credits</li>
        <li><strong>Gemini di Android Studio</strong></li>
        <li><strong>5 TB Storage</strong> — Foto, Drive, Gmail</li>
        <li><strong>Claude Sonnet &amp; Opus</strong> — Model Anthropic terbaru via Google AI Pro</li>
        <li><strong>Claude di Workspace</strong> — Terintegrasi di Docs, Gmail &amp; Sheets</li>
        <li><strong>Claude via Antigravity</strong> — Haiku, Sonnet &amp; Opus, enterprise-grade</li>
      </ul>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section" id="how-it-works">
  <h2>Cara Kerja</h2>
  <p class="subtitle">Proses sederhana — Google AI Pro langsung aktif di akun pribadi Anda</p>

  <div class="private-callout">
    <div class="private-callout__icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
    </div>
    <div>
      <div class="private-callout__title">Akun Pribadi — Bukan Shared Account</div>
      <div class="private-callout__sub">Google AI Pro Plan ini bersifat <strong>private</strong>. Langganan masuk langsung ke akun Google Anda sendiri via undangan resmi — bukan akun bersama, bukan password sharing.</div>
    </div>
  </div>

  <div class="steps-grid">
    <?php
    $steps = [
      ['1','Masukkan Email Tujuan','Isi alamat Gmail yang akan menerima undangan Google AI Pro Plan'],
      ['2','Lakukan Pembayaran','Scan QRIS via GoPay, OVO, DANA, atau e-wallet lainnya'],
      ['3','Verifikasi Otomatis','Sistem memverifikasi pembayaran Anda secara real-time'],
      ['4','Cek Inbox Gmail','Email Gift Offer dari Google tiba dalam 1–10 menit. Klik "Claim Offer" dan selesai!'],
    ];
    foreach ($steps as [$n, $t, $d]):
    ?>
    <div class="step-item">
      <div class="step-circle"><?= $n ?></div>
      <div class="step-item__title"><?= $t ?></div>
      <div class="step-item__desc"><?= $d ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="gift-explainer">
    <div class="gift-explainer__icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
    </div>
    <div>
      <div class="gift-explainer__title">Bagaimana email Gift Offer dari Google bekerja?</div>
      <div class="gift-explainer__text">Setelah pembayaran terkonfirmasi, Google akan mengirim email resmi bertajuk <strong>"You have a new Google One offer"</strong> ke Gmail Anda. Klik tombol <strong>"Claim offer"</strong> di dalam email, dan Google AI Pro langsung aktif pada akun Anda — tanpa perlu memasukkan password atau data apapun.</div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testi-section">
  <div class="testi-section-inner">
    <h2>Apa Kata Pengguna Kami</h2>
    <p class="subtitle">Lebih dari 1.200 pengguna telah merasakan manfaatnya</p>
    <div class="testi-grid">
      <div class="testi-card">
        <div class="testi-stars"><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span></div>
        <div class="testi-text">"Prosesnya gampang banget. Masukin email, bayar QRIS, tunggu sebentar — langsung ada email dari Google masuk. Klik Claim Offer, selesai. Gemini Pro langsung aktif di akun saya sendiri."</div>
        <div class="testi-author">
          <div class="testi-avatar" style="background:#e8f0fe;color:#1a73e8">R</div>
          <div><div class="testi-name">Rizky Firmansyah</div><div class="testi-loc">Jakarta &middot; Content Creator</div></div>
        </div>
      </div>
      <div class="testi-card">
        <div class="testi-stars"><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span></div>
        <div class="testi-text">"Tadinya khawatir ini shared account, tapi ternyata beneran masuk ke akun Google saya sendiri. Emailnya langsung dari Google. Satu klik, langsung aktif. Recommended!"</div>
        <div class="testi-author">
          <div class="testi-avatar" style="background:#e6f4ea;color:#34a853">D</div>
          <div><div class="testi-name">Dwi Oktavia</div><div class="testi-loc">Surabaya &middot; Freelance Designer</div></div>
        </div>
      </div>
      <div class="testi-card">
        <div class="testi-stars"><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span><span>&#9733;</span></div>
        <div class="testi-text">"Sudah 2 kali perpanjang, selalu lancar. Veo buat bikin video klien jauh lebih cepat, Deep Research juga ngebantu banget. Harganya jauh lebih terjangkau dari langganan langsung ke Google."</div>
        <div class="testi-author">
          <div class="testi-avatar" style="background:#fff8e1;color:#d97706">H</div>
          <div><div class="testi-name">Hendra Kusuma</div><div class="testi-loc">Bandung &middot; Digital Marketer</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="faq-section">
  <h2>FAQ</h2>
  <?php
  $faqs = [
    ['Apakah ini resmi dari Google?', 'Ya. Sistem kami mengirimkan Google AI Pro langsung sebagai <strong>Google Gift</strong> resmi ke akun Google Anda. Link yang Anda terima adalah undangan asli dari server Google, bukan pihak ketiga.'],
    ['Berapa lama proses aktivasi?', 'Pembayaran dicek secara otomatis oleh sistem. Setelah terverifikasi, Gift Google AI Pro langsung dikirim ke email Anda — biasanya dalam hitungan menit.'],
    ['Metode pembayaran apa yang diterima?', 'Saat ini hanya QRIS — bisa dibayar lewat GoPay, OVO, Dana, QRIS BCA, Shopeepay, dan semua dompet digital yang mendukung QRIS.'],
    ['Apakah ada garansi?', 'Ya, jika ada masalah dengan aktivasi dalam 7 hari pertama, kami akan refund penuh.'],
    ['Bagaimana cara kerja Google Gift?', 'Setelah pembayaran dikonfirmasi, sistem kami akan mengirimkan Google AI Pro sebagai &ldquo;gift&rdquo; resmi ke email Anda. Anda cukup klik tombol &ldquo;Claim Gift&rdquo; di email dan layanan langsung aktif di akun Google Anda.'],
  ];
  foreach ($faqs as [$q, $a]):
  ?>
  <details>
    <summary>
      <?= $q ?>
      <span class="faq-icon">+</span>
    </summary>
    <div class="faq-body"><?= $a ?></div>
  </details>
  <?php endforeach; ?>
</section>

<!-- FOOTER -->
<?php $tgContact = Config::get('telegram_contact', ''); ?>
<footer class="footer">
  <?php if ($tgContact): ?>
  <div class="footer__telegram">
    <a href="https://t.me/<?= htmlspecialchars(ltrim($tgContact, '@')) ?>" target="_blank" rel="noopener" class="footer__tg-link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
      Hubungi Admin: <strong>@<?= htmlspecialchars(ltrim($tgContact, '@')) ?></strong>
    </a>
  </div>
  <?php endif; ?>
  <p>&copy; <?= date('Y') ?> Google AI Pro Reseller. Bukan afiliasi resmi Google LLC.</p>
  <p style="margin-top:6px"><a href="#">Syarat &amp; Ketentuan</a> &middot; <a href="#">Kebijakan Privasi</a></p>
</footer>

<script>
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) { e.preventDefault(); target.scrollIntoView({behavior:'smooth', block:'start'}); }
  });
});

const observer = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); } });
}, { threshold: .1 });
document.querySelectorAll('.feature-card,.fade-up').forEach(el => observer.observe(el));

document.querySelectorAll('details').forEach(d => {
  d.addEventListener('toggle', () => {
    const icon = d.querySelector('.faq-icon');
    if (icon) icon.textContent = d.open ? '−' : '+';
  });
});
</script>
</body>
</html>
