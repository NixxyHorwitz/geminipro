<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
use App\Config;

$siteTitle   = Config::get('site_title', 'AI Tools Premium');
$siteTagline = Config::get('site_tagline', 'Platform Langganan Layanan AI Terpercaya');
$favicon     = Config::get('favicon_file', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($siteTitle) ?> — Layanan AI Premium Terpercaya</title>
<meta name="description" content="Platform terpercaya untuk berlangganan layanan AI premium. Akses teknologi kecerdasan buatan terdepan dengan harga terjangkau dan aktivasi instan.">
<meta name="keywords" content="AI premium, layanan kecerdasan buatan, teknologi AI, langganan AI, produktivitas digital">
<meta property="og:title" content="<?= htmlspecialchars($siteTitle) ?> — Layanan AI Premium">
<meta property="og:description" content="Platform terpercaya untuk berlangganan layanan AI premium. Aktivasi instan, garansi resmi.">
<meta property="og:type" content="website">
<?php if ($favicon): ?>
<link rel="icon" href="/assets/img/<?= htmlspecialchars($favicon) ?>?v=<?= time() ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Google+Sans+Display:wght@400;500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
:root {
  --blue:   #1a73e8;
  --blue-2: #1557b0;
  --green:  #1e8e3e;
  --text:   #202124;
  --text-2: #5f6368;
  --text-3: #9aa0a6;
  --bg:     #ffffff;
  --bg-2:   #f8f9fa;
  --bg-3:   #e8f0fe;
  --border: #e0e0e0;
  --radius: 12px;
  --shadow: 0 1px 6px rgba(32,33,36,.12);
  --font:   'Google Sans', 'Inter', system-ui, sans-serif;
  --font-d: 'Google Sans Display', 'Google Sans', sans-serif;
}
body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }

/* ── HEADER ── */
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 48px;
  height: 64px;
  background: #fff;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 100;
}
.header__logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-family: var(--font);
  font-size: 18px;
  font-weight: 500;
  color: var(--text);
}
.header__logo svg { width: 28px; height: 28px; }
.header__nav { display: flex; align-items: center; gap: 8px; }
.btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 20px;
  font-family: var(--font);
  font-size: 14px;
  font-weight: 500;
  border-radius: 24px;
  border: none;
  cursor: pointer;
  transition: background .15s, box-shadow .15s;
  text-decoration: none;
}
.btn--outline {
  background: transparent;
  color: var(--blue);
  border: 1.5px solid var(--blue);
}
.btn--outline:hover { background: #e8f0fe; }
.btn--primary {
  background: var(--blue);
  color: #fff;
}
.btn--primary:hover { background: var(--blue-2); box-shadow: 0 1px 6px rgba(26,115,232,.35); }

/* ── HERO ── */
.hero {
  max-width: 820px;
  margin: 0 auto;
  padding: 80px 24px 64px;
  text-align: center;
}
.hero__chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--bg-3);
  color: var(--blue);
  font-size: 13px;
  font-weight: 500;
  padding: 5px 14px;
  border-radius: 100px;
  margin-bottom: 28px;
}
.hero__title {
  font-family: var(--font-d);
  font-size: clamp(36px, 6vw, 56px);
  font-weight: 400;
  line-height: 1.15;
  letter-spacing: -.02em;
  color: var(--text);
  margin-bottom: 20px;
}
.hero__title strong { font-weight: 500; }
.hero__subtitle {
  font-size: 17px;
  color: var(--text-2);
  max-width: 580px;
  margin: 0 auto 36px;
  line-height: 1.7;
}
.hero__cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.btn--lg { padding: 13px 28px; font-size: 15px; border-radius: 28px; }

/* ── TRUST STRIP ── */
.trust-strip {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px 32px;
  padding: 20px 24px;
  background: var(--bg-2);
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
}
.trust-strip__item {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  color: var(--text-2);
  font-weight: 500;
}
.trust-strip__item svg { color: var(--green); flex-shrink: 0; }

/* ── PRODUCT GRID ── */
.products {
  max-width: 1000px;
  margin: 0 auto;
  padding: 72px 24px;
  text-align: center;
}
.products h2 {
  font-family: var(--font-d);
  font-size: clamp(26px, 4vw, 38px);
  font-weight: 400;
  color: var(--text);
  margin-bottom: 10px;
}
.products__sub {
  font-size: 16px;
  color: var(--text-2);
  margin-bottom: 48px;
}
.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  text-align: left;
}
.product-card {
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 28px 24px;
  background: #fff;
  transition: box-shadow .2s, border-color .2s;
  cursor: pointer;
}
.product-card:hover {
  box-shadow: 0 4px 20px rgba(32,33,36,.12);
  border-color: #c5d9f5;
}
.product-card__icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}
.product-card__name {
  font-size: 16px;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 6px;
}
.product-card__desc {
  font-size: 13px;
  color: var(--text-2);
  line-height: 1.6;
}
.product-card__badge {
  display: inline-block;
  background: #e6f4ea;
  color: var(--green);
  font-size: 11px;
  font-weight: 600;
  padding: 2px 10px;
  border-radius: 100px;
  margin-top: 12px;
}

/* ── HOW IT WORKS ── */
.how {
  background: var(--bg-2);
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  padding: 64px 24px;
  text-align: center;
}
.how h2 {
  font-family: var(--font-d);
  font-size: clamp(24px, 4vw, 34px);
  font-weight: 400;
  margin-bottom: 8px;
}
.how__sub { font-size: 15px; color: var(--text-2); margin-bottom: 40px; }
.how-steps {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
  max-width: 800px;
  margin: 0 auto;
}
.how-step {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px 20px;
  flex: 1 1 160px;
  max-width: 200px;
  text-align: center;
}
.how-step__num {
  width: 36px;
  height: 36px;
  background: var(--blue);
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
}
.how-step__title { font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.how-step__desc  { font-size: 12px; color: var(--text-2); line-height: 1.6; }

/* ── CTA BAND ── */
.cta-band {
  max-width: 660px;
  margin: 64px auto;
  padding: 0 24px;
  text-align: center;
}
.cta-band h2 {
  font-family: var(--font-d);
  font-size: clamp(26px, 4vw, 36px);
  font-weight: 400;
  margin-bottom: 12px;
}
.cta-band p { font-size: 16px; color: var(--text-2); margin-bottom: 28px; }

/* ── FOOTER ── */
.footer {
  border-top: 1px solid var(--border);
  padding: 28px 24px;
  text-align: center;
  font-size: 13px;
  color: var(--text-3);
}
.footer a { color: var(--text-2); }
.footer a:hover { color: var(--blue); }

/* ── ANIMATIONS ── */
.fade-in { opacity: 0; transform: translateY(18px); transition: opacity .55s ease, transform .55s ease; }
.fade-in.visible { opacity: 1; transform: none; }

/* ── RESPONSIVE ── */
@media (max-width: 640px) {
  .header { padding: 0 16px; }
  .hero { padding: 56px 16px 48px; }
  .hero__cta { flex-direction: column; align-items: center; }
  .hero__cta .btn { width: 100%; justify-content: center; max-width: 320px; }
  .product-grid { grid-template-columns: 1fr; }
  .how-steps { flex-direction: column; align-items: center; }
  .how-step { max-width: 100%; }
}
</style>
</head>
<body>

<!-- HEADER -->
<header class="header">
  <div class="header__logo">
    <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect width="28" height="28" rx="6" fill="#1a73e8"/>
      <path d="M14 6C10.84 11.47 7.47 14.84 2 16c5.47 1.16 8.84 4.53 10 10 1.16-5.47 4.53-8.84 10-10C16.53 14.84 13.16 11.47 14 6z" fill="white"/>
    </svg>
    <?= htmlspecialchars($siteTitle) ?>
  </div>
  <nav class="header__nav">
    <a href="/plan" class="btn btn--outline">Lihat Paket</a>
    <a href="/checkout" class="btn btn--primary">Mulai Sekarang</a>
  </nav>
</header>

<!-- HERO -->
<section class="hero">
  <div class="hero__chip fade-in">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L9 9H2l5.5 4.5L5 21l7-4.5 7 4.5-2.5-7.5L22 9h-7L12 2z"/></svg>
    Platform AI Premium Terpercaya
  </div>
  <h1 class="hero__title fade-in">
    Produktivitas Lebih Tinggi<br>dengan <strong>Kecerdasan Buatan</strong>
  </h1>
  <p class="hero__subtitle fade-in">
    Akses layanan AI generasi terbaru untuk riset mendalam, pembuatan konten, pengembangan kode, dan kreativitas tanpa batas — semua dalam satu paket terjangkau.
  </p>
  <div class="hero__cta fade-in">
    <a href="/plan" class="btn btn--primary btn--lg" id="hero-main-cta">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C10.84 7.47 7.47 10.84 2 12c5.47 1.16 8.84 4.53 10 10 1.16-5.47 4.53-8.84 10-10C16.53 10.84 13.16 7.47 12 2z"/></svg>
      Lihat Paket Lengkap
    </a>
    <a href="#how-it-works" class="btn btn--outline btn--lg" id="hero-learn-cta">Cara Kerja</a>
  </div>
</section>

<!-- TRUST STRIP -->
<div class="trust-strip">
  <div class="trust-strip__item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    Aktivasi Instan
  </div>
  <div class="trust-strip__item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    Pembayaran QRIS Aman
  </div>
  <div class="trust-strip__item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    Garansi 7 Hari
  </div>
  <div class="trust-strip__item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    1.200+ Pengguna Aktif
  </div>
</div>

<!-- PRODUCT SHOWCASE -->
<section class="products" id="products">
  <h2 class="fade-in">Teknologi AI Terdepan, Satu Paket</h2>
  <p class="products__sub fade-in">Kami menyediakan akses ke ekosistem AI terlengkap yang tersedia saat ini</p>
  <div class="product-grid">
    <div class="product-card fade-in" onclick="location.href='/plan'">
      <div class="product-card__icon" style="background:#e8f0fe">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#1a73e8"><path d="M12 2C10.84 7.47 7.47 10.84 2 12c5.47 1.16 8.84 4.53 10 10 1.16-5.47 4.53-8.84 10-10C16.53 10.84 13.16 7.47 12 2z"/></svg>
      </div>
      <div class="product-card__name">Asisten AI Percakapan</div>
      <div class="product-card__desc">Model AI multi-modal paling canggih untuk penelitian, analisis, dan percakapan mendalam.</div>
      <span class="product-card__badge">Tersedia</span>
    </div>
    <div class="product-card fade-in" onclick="location.href='/plan'">
      <div class="product-card__icon" style="background:#fce8e6">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#ea4335"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
      </div>
      <div class="product-card__name">Pembuatan Video AI</div>
      <div class="product-card__desc">Buat video sinematik berkualitas tinggi dari teks atau gambar — teknologi generasi terbaru.</div>
      <span class="product-card__badge">Tersedia</span>
    </div>
    <div class="product-card fade-in" onclick="location.href='/plan'">
      <div class="product-card__icon" style="background:#e6f4ea">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#1e8e3e"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
      </div>
      <div class="product-card__name">Generasi Gambar AI</div>
      <div class="product-card__desc">Buat gambar fotorealistis dan karya seni dalam hitungan detik menggunakan model terdepan.</div>
      <span class="product-card__badge">Tersedia</span>
    </div>
    <div class="product-card fade-in" onclick="location.href='/plan'">
      <div class="product-card__icon" style="background:#fff8e1">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#f29900"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
      </div>
      <div class="product-card__name">Riset Mendalam AI</div>
      <div class="product-card__desc">Otomatisasi riset dengan sumber valid. Hemat berjam-jam kerja riset manual.</div>
      <span class="product-card__badge">Tersedia</span>
    </div>
    <div class="product-card fade-in" onclick="location.href='/plan'">
      <div class="product-card__icon" style="background:#f3f0ff">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#7c3aed"><path d="M12 2L8.5 8.5 2 12l6.5 3.5L12 22l3.5-6.5L22 12l-6.5-3.5L12 2zm0 3.8l2.4 4.5 4.8 2.7-4.8 2.7L12 20.2l-2.4-4.5L4.8 13l4.8-2.7L12 5.8z"/></svg>
      </div>
      <div class="product-card__name">AI Reasoning Canggih</div>
      <div class="product-card__desc">Model penalaran kompleks untuk coding, analisis dokumen, dan pemecahan masalah mendalam.</div>
      <span class="product-card__badge">Tersedia</span>
    </div>
    <div class="product-card fade-in" onclick="location.href='/plan'">
      <div class="product-card__icon" style="background:#e8f0fe">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#1a73e8"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg>
      </div>
      <div class="product-card__name">Penyimpanan Cloud 5 TB</div>
      <div class="product-card__desc">Kapasitas penyimpanan besar untuk foto, dokumen, dan email tanpa khawatir kehabisan ruang.</div>
      <span class="product-card__badge">Tersedia</span>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how" id="how-it-works">
  <h2 class="fade-in">Cara Berlangganan</h2>
  <p class="how__sub fade-in">Proses sederhana, layanan aktif dalam hitungan menit</p>
  <div class="how-steps">
    <div class="how-step fade-in">
      <div class="how-step__num">1</div>
      <div class="how-step__title">Pilih Paket</div>
      <div class="how-step__desc">Buka halaman paket dan pilih layanan yang sesuai kebutuhan Anda</div>
    </div>
    <div class="how-step fade-in">
      <div class="how-step__num">2</div>
      <div class="how-step__title">Masukkan Email</div>
      <div class="how-step__desc">Isi alamat Gmail aktif Anda sebagai tujuan aktivasi layanan</div>
    </div>
    <div class="how-step fade-in">
      <div class="how-step__num">3</div>
      <div class="how-step__title">Bayar via QRIS</div>
      <div class="how-step__desc">Scan QRIS menggunakan GoPay, OVO, DANA, atau dompet digital lainnya</div>
    </div>
    <div class="how-step fade-in">
      <div class="how-step__num">4</div>
      <div class="how-step__title">Aktif Seketika</div>
      <div class="how-step__desc">Undangan resmi dikirim ke Gmail Anda. Klik "Claim" dan langsung aktif</div>
    </div>
  </div>
</section>

<!-- CTA BAND -->
<section class="cta-band">
  <h2 class="fade-in">Siap Meningkatkan Produktivitas Anda?</h2>
  <p class="fade-in">Bergabung dengan lebih dari 1.200 pengguna yang sudah merasakan manfaat layanan AI premium kami.</p>
  <div class="fade-in">
    <a href="/plan" class="btn btn--primary btn--lg" id="bottom-cta">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C10.84 7.47 7.47 10.84 2 12c5.47 1.16 8.84 4.53 10 10 1.16-5.47 4.53-8.84 10-10C16.53 10.84 13.16 7.47 12 2z"/></svg>
      Lihat Paket &amp; Harga
    </a>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle) ?>. Platform layanan digital berlangganan.</p>
  <p style="margin-top:6px"><a href="#">Syarat &amp; Ketentuan</a> &middot; <a href="#">Kebijakan Privasi</a></p>
</footer>

<script>
// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const t = document.querySelector(a.getAttribute('href'));
    if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
  });
});

// Fade-in on scroll
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.fade-in').forEach(el => obs.observe(el));
</script>
</body>
</html>
