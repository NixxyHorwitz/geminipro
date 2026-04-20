<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Config;

// First-time password setup — only accessible if no password is set yet
$existingHash = Config::get('console_password_hash', '');

// If password already set AND user is not logged in, block
if ($existingHash !== '' && empty($_SESSION['console_admin'])) {
    header('Location: /console/login.php'); exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass1) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif (!preg_match('/[A-Za-z]/', $pass1) || !preg_match('/[0-9]/', $pass1)) {
        $error = 'Password harus mengandung huruf dan angka.';
    } else {
        $hash = password_hash($pass1, PASSWORD_BCRYPT, ['cost' => 12]);
        if ($pdo) {
            Config::set($pdo, 'console_password_hash', $hash);
        }
        $success = 'Password berhasil disimpan!';
        // Auto-login after set
        session_regenerate_id(true);
        $_SESSION['console_admin']       = true;
        $_SESSION['console_last_rotate'] = time();
        header('Location: /console/'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set Password Admin — Google AI Pro Console</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/assets/css/console.css">
</head>
<body class="login-body">
  <div class="login-card">
    <div class="login-logo">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect width="40" height="40" rx="10" fill="#4285F4"/>
        <path d="M28 20.4C28 17.2 25.5 14.5 22 14H20v6h6v-2a2 2 0 010 4h-6v5.9C24.6 27.3 28 24.1 28 20.4z" fill="white"/>
        <path d="M20 14h-6v6h6v-6z" fill="#FBBC04"/>
        <path d="M14 20v6h6v-6h-6z" fill="white"/>
      </svg>
    </div>
    <div class="login-brand">Google AI Pro</div>
    <div class="login-title"><?= $existingHash ? 'Ubah Password Admin' : 'Buat Password Admin' ?></div>
    <div class="login-sub">
      <?= $existingHash ? 'Masukkan password baru untuk console admin' : 'Setup pertama kali — tentukan password admin console' ?>
    </div>

    <?php if ($error): ?>
    <div class="alert alert--error" style="margin-bottom:20px">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Password Baru</label>
        <input type="password" name="password" class="form-control" 
               placeholder="Min. 8 karakter, harus ada angka & huruf" required>
      </div>
      <div class="form-group">
        <label class="form-label">Konfirmasi Password</label>
        <input type="password" name="password2" class="form-control" placeholder="Ulangi password" required>
      </div>
      <div class="password-rules">
        <div>✓ Minimal 8 karakter</div>
        <div>✓ Mengandung huruf & angka</div>
      </div>
      <button type="submit" class="btn btn--primary btn--full btn--lg" style="margin-top:16px">
        <?= $existingHash ? 'Simpan Password Baru' : 'Buat Password & Masuk' ?>
      </button>
    </form>
    <div class="login-footer">Google AI Pro Admin Console &copy; <?= date('Y') ?></div>
  </div>
</body>
</html>
