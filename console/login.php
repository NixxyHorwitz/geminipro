<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Config;

// If already logged in, go to dashboard
if (!empty($_SESSION['console_admin'])) {
    header('Location: /console/'); exit;
}

$error  = '';
$next   = $_GET['next'] ?? '/console/';

// Rate limiting via session (simple: max 5 attempts per 10 min)
$now      = time();
$attempts = $_SESSION['login_attempts'] ?? 0;
$lockUntil= $_SESSION['login_lock_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($now < $lockUntil) {
        $wait  = ceil(($lockUntil - $now) / 60);
        $error = "Terlalu banyak percobaan. Coba lagi dalam {$wait} menit.";
    } else {
        $password = $_POST['password'] ?? '';
        $adminHash= Config::get('console_password_hash', '');

        if ($adminHash === '') {
            header('Location: /console/passwd.php'); exit;
        }

        if (password_verify($password, $adminHash)) {
            // Success — reset attempts + set session
            unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
            session_regenerate_id(true);
            $_SESSION['console_admin']       = true;
            $_SESSION['console_last_rotate'] = time();
            $next = preg_replace('/[^\/a-zA-Z0-9_\-\.=&?%]/', '', $next);
            header("Location: {$next}"); exit;
        } else {
            $_SESSION['login_attempts'] = ($attempts + 1);
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_lock_until'] = $now + 600; // 10 min lock
                $error = 'Akun terkunci 10 menit karena terlalu banyak percobaan.';
            } else {
                $left  = 5 - $_SESSION['login_attempts'];
                $error = "Password salah. Sisa percobaan: {$left}";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — Google AI Pro Console</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
    <div class="login-title">Masuk ke Console Admin</div>
    <div class="login-sub">Akses penuh ke pengelolaan platform</div>

    <?php if ($error): ?>
    <div class="alert alert--error" style="margin-bottom:20px">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="login-form">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <div class="form-group">
        <label class="form-label">Password Admin</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" class="form-control" 
                 placeholder="Masukkan password" autofocus autocomplete="current-password" required>
          <button type="button" class="input-toggle" onclick="togglePass()">
            <svg id="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn--primary btn--full btn--lg" style="margin-top:8px">
        Masuk ke Console
      </button>
    </form>

    <div class="login-footer">
      Google AI Pro Admin Console &copy; <?= date('Y') ?>
    </div>
  </div>
<script>
function togglePass() {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
