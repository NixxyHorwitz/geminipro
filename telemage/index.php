<?php
session_start();

$PIN = '292008';
$imageDir = __DIR__ . '/../uploads/telemage/';
$imageUrlBase = '../uploads/telemage/';

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Handle Login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    if ($_POST['pin'] === $PIN) {
        $_SESSION['telemage_auth'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'PIN yang dimasukkan salah!';
    }
}

// Check auth status
$isAuthenticated = isset($_SESSION['telemage_auth']) && $_SESSION['telemage_auth'] === true;

// Handle Delete
if ($isAuthenticated && isset($_POST['delete_img'])) {
    $img = basename($_POST['delete_img']);
    $imgPath = $imageDir . $img;
    if (file_exists($imgPath)) {
        unlink($imgPath);
    }
    header('Location: index.php?deleted=1');
    exit;
}

// Get images
$images = [];
if ($isAuthenticated) {
    if (!is_dir($imageDir)) {
        // Create if it doesn't exist to prevent errors
        @mkdir($imageDir, 0755, true);
    }
    
    if (is_dir($imageDir)) {
        $files = scandir($imageDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file($imageDir . $file)) {
                $images[] = $file;
            }
        }
        // sort newest first
        usort($images, function($a, $b) use ($imageDir) {
            return filemtime($imageDir . $b) - filemtime($imageDir . $a);
        });
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemage | Secure Photo Gallery</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --danger: #ef4444;
            --danger-hover: #dc2828;
            --text: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text);
            min-height: 100vh;
            background-image: radial-gradient(circle at top right, #1e1b4b, #0f172a);
            background-attachment: fixed;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(to right, #60a5fa, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-outline {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text);
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.1);
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.39);
        }
        
        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.23);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: white;
        }

        /* Auth screen */
        .auth-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 80vh;
        }

        .auth-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 3rem;
            border-radius: 1rem;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .auth-card svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1.5rem;
            color: #60a5fa;
            filter: drop-shadow(0 0 10px rgba(96, 165, 250, 0.5));
        }

        .auth-card h2 {
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .auth-card p {
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.3);
            color: white;
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.5rem;
            transition: all 0.3s;
        }
        
        .form-control::placeholder {
            color: rgba(255,255,255,0.2);
            letter-spacing: normal;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            background: rgba(0,0,0,0.5);
        }

        .btn-block {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            border-color: rgba(255,255,255,0.15);
        }

        .card-img-wrapper {
            position: relative;
            width: 100%;
            padding-top: 75%; /* 4:3 Aspect Ratio */
            overflow: hidden;
            background: rgba(0,0,0,0.5);
        }

        .card-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .card:hover .card-img {
            transform: scale(1.05);
        }

        .card-body {
            padding: 1.25rem;
        }

        .card-title {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: monospace;
            background: rgba(0,0,0,0.3);
            padding: 0.5rem;
            border-radius: 0.25rem;
        }

        .card-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .card-actions > * {
            flex: 1;
        }
        
        .card-actions form {
            flex: 1;
            display: flex;
        }

        .card-actions form button {
            width: 100%;
        }

        .empty-state {
            text-align: center;
            padding: 5rem 0;
            color: var(--text-muted);
            grid-column: 1 / -1;
            background: rgba(30, 41, 59, 0.3);
            border-radius: 1rem;
            border: 1px dashed rgba(255,255,255,0.1);
        }
        
        .empty-state svg {
            width: 4rem;
            height: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (!$isAuthenticated): ?>
    
    <div class="auth-wrapper">
        <div class="auth-card">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <h2>Telemage Vault</h2>
            <p>Masukkan PIN statis untuk mengakses galeri.</p>
            
            <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <input type="password" name="pin" class="form-control" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;" required autocomplete="off" autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    Buka Akses
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>

    <header>
        <h1>
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Telemage
        </h1>
        <a href="?logout=1" class="btn btn-outline">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Keluar
        </a>
    </header>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Gambar berhasil dihapus dari server.
        </div>
    <?php endif; ?>

    <div class="grid">
        <?php if (empty($images)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3>Belum ada gambar</h3>
                <p>Kirimkan gambar ke bot Telegram untuk melihatnya di sini.</p>
            </div>
        <?php else: ?>
            <?php foreach ($images as $img): ?>
                <div class="card">
                    <div class="card-img-wrapper">
                        <img src="<?php echo htmlspecialchars($imageUrlBase . $img); ?>" alt="Upload" class="card-img" loading="lazy">
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($img); ?></div>
                        <div class="card-actions">
                            <a href="<?php echo htmlspecialchars($imageUrlBase . $img); ?>" target="_blank" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                Open
                            </a>
                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus gambar ini?');">
                                <input type="hidden" name="delete_img" value="<?php echo htmlspecialchars($img); ?>">
                                <button type="submit" class="btn btn-danger">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

</body>
</html>
