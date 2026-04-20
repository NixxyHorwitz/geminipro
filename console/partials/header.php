<?php
// Shared console layout header
// $pageTitle must be set before including this
$pageTitle = $pageTitle ?? 'Console';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> — Admin Console</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/css/console.css">
</head>
<body class="console-body">

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar__logo">
    <svg width="28" height="28" viewBox="0 0 40 40" fill="none">
      <rect width="40" height="40" rx="8" fill="#4285F4"/>
      <path d="M28 20.4C28 17.2 25.5 14.5 22 14H20v6h6v-2a2 2 0 010 4h-6v5.9C24.6 27.3 28 24.1 28 20.4z" fill="white"/>
      <path d="M20 14h-6v6h6v-6z" fill="#FBBC04"/>
      <path d="M14 20v6h6v-6h-6z" fill="white"/>
    </svg>
    <div class="sidebar__brand">
      <div class="sidebar__name">Google AI Pro</div>
      <div class="sidebar__sub">Admin Console</div>
    </div>
  </div>

  <nav class="sidebar__nav">
    <div class="sidebar__label">Utama</div>
    <a href="/console/" class="sidebar__link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="/console/orders.php" class="sidebar__link <?= $activePage === 'orders' ? 'active' : '' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      Orders
      <?php
      // Badge for pending orders
      if (isset($pdo)) {
          try {
              $cnt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
              if ($cnt > 0) echo "<span class=\"sidebar__badge\">{$cnt}</span>";
          } catch(\Exception $e) {}
      }
      ?>
    </a>
    <a href="/console/traffic.php" class="sidebar__link <?= $activePage === 'traffic' ? 'active' : '' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Traffic & Stats
    </a>

    <div class="sidebar__label">Konfigurasi</div>
    <a href="/console/qris.php" class="sidebar__link <?= $activePage === 'qris' ? 'active' : '' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 00-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 01-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>
      Set QRIS
    </a>
    <a href="/console/settings.php" class="sidebar__link <?= $activePage === 'settings' ? 'active' : '' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      Settings
    </a>
    <a href="/console/passwd.php" class="sidebar__link <?= $activePage === 'passwd' ? 'active' : '' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
      Password Admin
    </a>
  </nav>

  <div class="sidebar__footer">
    <a href="/" target="_blank" class="sidebar__link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      Lihat Website
    </a>
    <a href="/console/logout.php" class="sidebar__link sidebar__link--danger">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>
</aside>

<!-- Main area -->
<div class="console-main">
  <!-- Topbar -->
  <header class="topbar">
    <button class="topbar__toggle" id="sidebar-toggle" onclick="toggleSidebar()">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="topbar__title"><?= htmlspecialchars($pageTitle) ?></div>
    <div class="topbar__actions">
      <span class="topbar__time" id="topbar-time"></span>
      <div class="topbar__avatar">A</div>
    </div>
  </header>

  <!-- Page content injected below -->
  <div class="console-content">
