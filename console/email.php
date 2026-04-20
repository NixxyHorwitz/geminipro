<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
csrf_enforce();

use App\Config;
use App\Mailer;
use App\Order;

$flash     = '';
$flashType = 'success';
$tab       = $_GET['tab'] ?? 'smtp';

// ── AJAX: Test send email ─────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'test_send') {
    header('Content-Type: application/json');
    $m = Mailer::fromConfig();
    $ok = $m->send(
        trim($_POST['test_to'] ?? ''),
        '✅ Test Email — Google AI Pro',
        Mailer::buildCustomEmail(
            'Admin',
            'Test Email Berhasil!',
            "Ini adalah test email dari console admin Google AI Pro.\n\nJika Anda menerima email ini, konfigurasi SMTP sudah benar.",
        )
    );
    echo json_encode(['ok' => $ok, 'error' => $m->lastError]);
    exit;
}

// ── Save SMTP config ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_smtp') {
    $fields = [
        'smtp_host'      => trim($_POST['smtp_host']      ?? 'smtp.gmail.com'),
        'smtp_port'      => trim($_POST['smtp_port']      ?? '587'),
        'smtp_user'      => trim($_POST['smtp_user']      ?? ''),
        'smtp_pass'      => trim($_POST['smtp_pass']      ?? ''),
        'smtp_from'      => trim($_POST['smtp_from']      ?? ''),
        'smtp_from_name' => trim($_POST['smtp_from_name'] ?? 'Google AI Pro'),
        'smtp_secure'    => trim($_POST['smtp_secure']    ?? 'tls'),
    ];
    if (empty($fields['smtp_user'])) {
        $flash = 'Email SMTP tidak boleh kosong.'; $flashType = 'error';
    } else {
        foreach ($fields as $k => $v) {
            Config::set($pdo, $k, $v);
        }
        $flash = 'Konfigurasi SMTP berhasil disimpan!';
    }
    $tab = 'smtp';
}

// ── Send activation/custom email to order ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'send_activation') {
    $orderCode = trim($_POST['order_code'] ?? '');
    $linkUrl   = trim($_POST['activation_link'] ?? '');
    $ord = (new Order($pdo))->findByCode($orderCode);

    if (!$ord) {
        $flash = "Order {$orderCode} tidak ditemukan."; $flashType = 'error';
    } elseif (!$linkUrl) {
        $flash = 'Link aktivasi tidak boleh kosong.'; $flashType = 'error';
    } else {
        $m    = Mailer::fromConfig();
        $name = explode('@', $ord['email'])[0];
        $html = Mailer::buildActivationEmail($name, $linkUrl);
        $ok   = $m->send($ord['email'], 'Link Aktivasi Google AI Pro Anda', $html);
        if ($ok) {
            $flash = "Email aktivasi berhasil dikirim ke {$ord['email']}!";
        } else {
            $flash = "Gagal kirim email: {$m->lastError}"; $flashType = 'error';
        }
    }
    $tab = 'activation';
}

// ── Send custom/broadcast email ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'send_custom') {
    $toRaw   = trim($_POST['custom_to']      ?? '');
    $subject = trim($_POST['custom_subject'] ?? '');
    $body    = trim($_POST['custom_body']    ?? '');

    if (!$toRaw || !$subject || !$body) {
        $flash = 'Penerima, subjek, dan isi pesan wajib diisi.'; $flashType = 'error';
    } else {
        $m    = Mailer::fromConfig();
        $html = Mailer::buildCustomEmail('Pelanggan', $subject, $body);
        // Allow comma-separated multiple recipients
        $recipients = array_filter(array_map('trim', explode(',', $toRaw)));
        $failList = [];
        foreach ($recipients as $recipient) {
            if (!$m->send($recipient, $subject, $html)) {
                $failList[] = $recipient;
            }
        }
        if (empty($failList)) {
            $flash = 'Email berhasil dikirim ke ' . count($recipients) . ' penerima!';
        } else {
            $flash = 'Gagal kirim ke: ' . implode(', ', $failList) . '. Error: ' . $m->lastError;
            $flashType = 'error';
        }
    }
    $tab = 'custom';
}

// Load current config
Config::loadFromDb($pdo);
$cfg = [
    'smtp_host'      => Config::get('smtp_host',      'smtp.gmail.com'),
    'smtp_port'      => Config::get('smtp_port',      '587'),
    'smtp_user'      => Config::get('smtp_user',      ''),
    'smtp_pass'      => Config::get('smtp_pass',      ''),
    'smtp_from'      => Config::get('smtp_from',      ''),
    'smtp_from_name' => Config::get('smtp_from_name', 'Google AI Pro'),
    'smtp_secure'    => Config::get('smtp_secure',    'tls'),
];

// Load confirmed orders for activation tab
$orders = $pdo->query(
    "SELECT * FROM orders WHERE status IN ('confirmed','pending') ORDER BY created_at DESC LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = 'Email / SMTP';
$activePage = 'email';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Email & SMTP</h1>
    <p class="page-sub">Kirim email aktivasi, custom message, dan uji konfigurasi SMTP Google</p>
  </div>
  <div class="page-header__actions">
    <?php $smtpOk = !empty($cfg['smtp_user']); ?>
    <span class="badge badge--<?= $smtpOk ? 'success' : 'error' ?>">
      SMTP <?= $smtpOk ? 'Terkonfigurasi' : 'Belum Setup' ?>
    </span>
  </div>
</div>

<?php if ($flash): ?>
<div class="alert alert--<?= $flashType === 'error' ? 'error' : 'success' ?>" style="margin-bottom:20px">
  <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs">
  <a href="?tab=smtp"       class="tab-item <?= $tab==='smtp'       ?'active':'' ?>">⚙️ Setup SMTP</a>
  <a href="?tab=activation" class="tab-item <?= $tab==='activation' ?'active':'' ?>">🔗 Kirim Aktivasi</a>
  <a href="?tab=custom"     class="tab-item <?= $tab==='custom'     ?'active':'' ?>">✉️ Custom Email</a>
  <a href="?tab=preview"    class="tab-item <?= $tab==='preview'    ?'active':'' ?>">👁️ Preview Template</a>
</div>

<!-- ===== TAB: SMTP SETUP ===== -->
<?php if ($tab === 'smtp'): ?>
<div class="two-col-grid">
  <div class="card">
    <div class="card__header">
      <div class="card__title">Konfigurasi Google SMTP</div>
    </div>
    <div class="card__body">
      <div class="alert alert--info" style="margin-bottom:20px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        <div>Gunakan Gmail dengan <strong>App Password</strong>, bukan password Google biasa. 
          <a href="https://myaccount.google.com/apppasswords" target="_blank">Buat App Password →</a>
        </div>
      </div>
      <form method="POST" id="form-smtp">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_smtp">
        <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
          <div class="form-group">
            <label class="form-label">SMTP Host</label>
            <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($cfg['smtp_host']) ?>" placeholder="smtp.gmail.com">
          </div>
          <div class="form-group">
            <label class="form-label">Port</label>
            <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($cfg['smtp_port']) ?>" placeholder="587">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Security</label>
          <select name="smtp_secure" class="form-control">
            <option value="tls"  <?= $cfg['smtp_secure']==='tls'  ?'selected':'' ?>>TLS / STARTTLS (Port 587) — Direkomendasikan</option>
            <option value="ssl"  <?= $cfg['smtp_secure']==='ssl'  ?'selected':'' ?>>SSL (Port 465)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Email Gmail (Username)</label>
          <input type="email" name="smtp_user" class="form-control" value="<?= htmlspecialchars($cfg['smtp_user']) ?>" placeholder="yourname@gmail.com">
          <div class="form-hint">Gunakan email Gmail yang sudah aktif 2FA</div>
        </div>
        <div class="form-group">
          <label class="form-label">App Password Gmail</label>
          <div class="input-wrap">
            <input type="password" name="smtp_pass" id="smtp-pass" class="form-control" 
                   value="<?= htmlspecialchars($cfg['smtp_pass']) ?>" 
                   placeholder="xxxx xxxx xxxx xxxx">
            <button type="button" class="input-toggle" onclick="togglePass('smtp-pass')">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="form-hint">
            Bukan password Gmail biasa. 
            <a href="https://myaccount.google.com/u/0/apppasswords" target="_blank">Buat App Password di sini</a>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Email Pengirim (From)</label>
            <input type="email" name="smtp_from" class="form-control" value="<?= htmlspecialchars($cfg['smtp_from']) ?>" placeholder="noreply@gmail.com">
          </div>
          <div class="form-group">
            <label class="form-label">Nama Pengirim</label>
            <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($cfg['smtp_from_name']) ?>">
          </div>
        </div>
        <button type="submit" class="btn btn--primary btn--full">Simpan Konfigurasi SMTP</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card__header">
      <div class="card__title">Test Kirim Email</div>
    </div>
    <div class="card__body">
      <p style="font-size:13px;color:var(--c-text-sec);margin-bottom:20px">
        Kirim email percobaan ke alamat Anda untuk memverifikasi bahwa SMTP sudah berjalan dengan benar.
      </p>
      <div class="form-group">
        <label class="form-label">Kirim Test Ke</label>
        <input type="email" id="test-to" class="form-control" placeholder="test@gmail.com">
      </div>
      <button type="button" class="btn btn--outline btn--full" id="btn-test-smtp" onclick="testSmtp()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Kirim Test Email
      </button>
      <div id="test-result" style="margin-top:14px;display:none"></div>

      <div class="divider" style="margin:24px 0"></div>

      <div class="card__title" style="margin-bottom:12px">Panduan App Password Gmail</div>
      <div class="guide-steps">
        <div class="guide-step">
          <div class="guide-step__num">1</div>
          <div><strong>Aktifkan 2-Step Verification</strong><br>
            <a href="https://myaccount.google.com/security" target="_blank" style="font-size:12px">myaccount.google.com/security →</a></div>
        </div>
        <div class="guide-step">
          <div class="guide-step__num">2</div>
          <div><strong>Buka App Passwords</strong><br>
            <a href="https://myaccount.google.com/apppasswords" target="_blank" style="font-size:12px">myaccount.google.com/apppasswords →</a></div>
        </div>
        <div class="guide-step">
          <div class="guide-step__num">3</div>
          <div><strong>Pilih "Mail" → "Other"</strong><br>
            <span style="font-size:12px;color:var(--c-text-sec)">Beri nama "Google AI Pro", klik Generate</span></div>
        </div>
        <div class="guide-step">
          <div class="guide-step__num">4</div>
          <div><strong>Salin 16-digit password</strong><br>
            <span style="font-size:12px;color:var(--c-text-sec)">Paste di field "App Password" di sebelah kiri</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== TAB: SEND ACTIVATION ===== -->
<?php elseif ($tab === 'activation'): ?>
<div class="two-col-grid">
  <div class="card">
    <div class="card__header">
      <div class="card__title">Kirim Link Aktivasi</div>
    </div>
    <div class="card__body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_activation">
        <div class="form-group">
          <label class="form-label">Pilih Order</label>
          <select name="order_code" id="order-select" class="form-control" onchange="fillEmail(this)">
            <option value="">-- Pilih Order --</option>
            <?php foreach ($orders as $o): ?>
            <option value="<?= htmlspecialchars($o['order_code']) ?>" 
                    data-email="<?= htmlspecialchars($o['email']) ?>"
                    data-status="<?= htmlspecialchars($o['status']) ?>">
              <?= htmlspecialchars($o['order_code']) ?> — <?= htmlspecialchars($o['email']) ?> 
              (<?= ucfirst($o['status']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Email Tujuan</label>
          <input type="email" id="target-email" class="form-control" readonly 
                 style="background:var(--c-bg);color:var(--c-text-sec)" placeholder="(auto dari order)">
        </div>
        <div class="form-group">
          <label class="form-label">Link Aktivasi</label>
          <input type="url" name="activation_link" class="form-control" 
                 placeholder="https://accounts.google.com/..." required>
          <div class="form-hint">URL link aktivasi Google AI Pro atau undangan yang akan dikirimkan ke pembeli</div>
        </div>
        <button type="submit" class="btn btn--primary btn--full">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Kirim Email Aktivasi
        </button>
      </form>
    </div>
  </div>

  <!-- Order list quick view -->
  <div class="card">
    <div class="card__header">
      <div class="card__title">Order Siap Aktivasi</div>
    </div>
    <div class="card__body" style="padding:0">
      <table class="table table--compact">
        <thead><tr><th>Kode</th><th>Email</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr style="cursor:pointer" onclick="selectOrder('<?= $o['order_code'] ?>','<?= htmlspecialchars($o['email']) ?>')">
            <td><code style="font-size:12px"><?= htmlspecialchars($o['order_code']) ?></code></td>
            <td style="font-size:13px;max-width:180px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($o['email']) ?></td>
            <td><span class="badge badge--<?= match($o['status']) { 'confirmed'=>'success','pending'=>'warn', default=>'neutral' } ?>"><?= ucfirst($o['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($orders)): ?>
          <tr><td colspan="3" style="text-align:center;color:var(--c-text-hint);padding:24px">Tidak ada order</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ===== TAB: CUSTOM EMAIL ===== -->
<?php elseif ($tab === 'custom'): ?>
<div class="card">
  <div class="card__header">
    <div class="card__title">Kirim Email Custom / Broadcast</div>
  </div>
  <div class="card__body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send_custom">
      <div class="form-group">
        <label class="form-label">Penerima</label>
        <input type="text" name="custom_to" class="form-control" 
               placeholder="email@gmail.com, email2@gmail.com (bisa multiple, pisah koma)">
        <div class="form-hint">
          Gunakan koma untuk beberapa penerima. 
          <button type="button" class="btn btn--xs btn--ghost" onclick="fillAllConfirmed()" style="margin-left:4px">
            Import semua order confirmed
          </button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Subjek Email</label>
        <input type="text" name="custom_subject" class="form-control" placeholder="Informasi Langganan Google AI Pro Anda">
      </div>
      <div class="form-group">
        <label class="form-label">Isi Pesan</label>
        <textarea name="custom_body" class="form-control" rows="8" 
                  placeholder="Ketik pesan Anda di sini...&#10;&#10;Newline akan dikonversi ke baris baru di email.&#10;&#10;Contoh:&#10;Halo,&#10;&#10;Terima kasih telah berlangganan Google AI Pro..."></textarea>
        <div class="form-hint">Plain text — akan diformat otomatis menjadi email HTML bergaya Google</div>
      </div>
      <div style="display:flex;gap:12px">
        <button type="submit" class="btn btn--primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Kirim Email
        </button>
        <button type="button" class="btn btn--ghost" onclick="previewEmail()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Preview
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Preview pane -->
<div id="preview-pane" class="card" style="margin-top:16px;display:none">
  <div class="card__header">
    <div class="card__title">Preview Email</div>
    <button onclick="document.getElementById('preview-pane').style.display='none'" class="btn btn--ghost btn--xs">Tutup</button>
  </div>
  <div class="card__body" style="padding:0">
    <iframe id="preview-iframe" style="width:100%;height:500px;border:none;border-radius:0 0 10px 10px"></iframe>
  </div>
</div>

<!-- ===== TAB: PREVIEW TEMPLATE ===== -->
<?php elseif ($tab === 'preview'): ?>
<div class="card">
  <div class="card__header">
    <div class="card__title">Preview Template Email Aktivasi</div>
  </div>
  <div class="card__body" style="padding:0">
    <iframe srcdoc="<?= htmlspecialchars(Mailer::buildActivationEmail(
        'John Doe',
        'https://accounts.google.com/activate?token=SAMPLE_TOKEN_HERE',
        Config::get('product_name', 'Google AI Pro')
    )) ?>" style="width:100%;height:600px;border:none;border-radius:0 0 10px 10px"></iframe>
  </div>
</div>
<?php endif; ?>

<!-- Hidden data for JS -->
<script>
const confirmedOrders = <?= json_encode(array_map(
    fn($o) => ['code' => $o['order_code'], 'email' => $o['email'], 'status' => $o['status']],
    array_filter($orders, fn($o) => $o['status'] === 'confirmed')
)) ?>;

function togglePass(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}

function fillEmail(sel) {
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('target-email').value = opt.dataset.email || '';
}

function selectOrder(code, email) {
  const sel = document.getElementById('order-select');
  if (sel) {
    sel.value = code;
    document.getElementById('target-email').value = email;
  }
}

function fillAllConfirmed() {
  const emails = confirmedOrders.map(o => o.email).join(', ');
  const inp = document.querySelector('[name=custom_to]');
  if (inp) inp.value = emails;
}

async function testSmtp() {
  const to  = document.getElementById('test-to').value.trim();
  const btn = document.getElementById('btn-test-smtp');
  const res = document.getElementById('test-result');
  if (!to) { alert('Masukkan email tujuan test.'); return; }

  btn.disabled = true;
  btn.textContent = 'Mengirim...';

  const fd = new FormData();
  fd.append('_csrf', '<?= csrf_token() ?>');
  fd.append('action', 'test_send');
  fd.append('test_to', to);

  try {
    const r    = await fetch(location.href, { method: 'POST', body: fd });
    const data = await r.json();
    res.style.display = 'block';
    if (data.ok) {
      res.innerHTML = '<div class="alert alert--success">✅ Email berhasil dikirim ke ' + to + '! Cek inbox Anda.</div>';
    } else {
      res.innerHTML = '<div class="alert alert--error">❌ Gagal: ' + (data.error || 'Unknown error') + '</div>';
    }
  } catch(e) {
    res.style.display = 'block';
    res.innerHTML = '<div class="alert alert--error">Error: ' + e.message + '</div>';
  }
  btn.disabled = false;
  btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Kirim Test Email';
}

function previewEmail() {
  const subject = document.querySelector('[name=custom_subject]').value || 'Preview';
  const body    = document.querySelector('[name=custom_body]').value || '';
  const frame   = document.getElementById('preview-iframe');
  const pane    = document.getElementById('preview-pane');

  // Simple client-side preview (mirrors server template)
  const contentHtml = body.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
  frame.srcdoc = `<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f8f9fa;font-family:Arial,sans-serif">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;padding:32px 0">
      <tr><td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">
          <tr><td style="background:linear-gradient(135deg,#1a73e8,#4285f4);padding:28px 32px;text-align:center">
            <div style="font-size:20px;font-weight:700;color:#fff">Google AI Pro</div>
          </td></tr>
          <tr><td style="padding:32px 40px">
            <h2 style="margin:0 0 16px;font-size:18px;color:#202124">${subject}</h2>
            <div style="font-size:14px;color:#5f6368;line-height:1.7">${contentHtml}</div>
          </td></tr>
          <tr><td style="background:#f8f9fa;padding:16px 32px;text-align:center;border-top:1px solid #e8eaed">
            <p style="font-size:12px;color:#9aa0a6;margin:0">&copy; ${new Date().getFullYear()} Google AI Pro Reseller</p>
          </td></tr>
        </table>
      </td></tr>
    </table>
  </body></html>`;

  pane.style.display = 'block';
  pane.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
