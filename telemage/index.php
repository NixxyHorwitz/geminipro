<?php
session_start();

$PIN = '292008';
$imageDir = __DIR__ . '/../uploads/telemage/';

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

// Serve Image securely (Bypassing .htaccess Deny from all)
if ($isAuthenticated && isset($_GET['img'])) {
    $img = basename($_GET['img']);
    $imgPath = $imageDir . $img;
    if (file_exists($imgPath) && is_file($imgPath)) {
        $mime = mime_content_type($imgPath);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($imgPath));
        readfile($imgPath);
        exit;
    } else {
        header("HTTP/1.0 404 Not Found");
        exit('Image not found');
    }
}

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
    <title>Telemage | Neobrutalist Vault</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FDFBF7;
            --main: #000000;
            --primary: #FF5A5F;
            --secondary: #00A699;
            --accent: #FFB400;
            --surface: #FFFFFF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Space Grotesk', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--main);
            min-height: 100vh;
            background-image: radial-gradient(var(--main) 1px, transparent 1px);
            background-size: 20px 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Neobrutalist UI elements */
        .neo-box {
            background: var(--surface);
            border: 3px solid var(--main);
            box-shadow: 6px 6px 0px var(--main);
            border-radius: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            cursor: pointer;
            border: 3px solid var(--main);
            border-radius: 6px;
            box-shadow: 4px 4px 0px var(--main);
            transition: all 0.1s ease;
            color: var(--main);
            text-transform: uppercase;
        }

        .btn:active {
            transform: translate(4px, 4px);
            box-shadow: 0px 0px 0px var(--main);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-accent {
            background: var(--accent);
        }

        .btn-danger {
            background: #ff3333;
            color: white;
        }

        .btn-light {
            background: var(--surface);
        }

        /* Header */
        header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 3rem;
            padding: 1.5rem;
            background: var(--accent);
            border: 3px solid var(--main);
            box-shadow: 6px 6px 0px var(--main);
            border-radius: 8px;
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: -1px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Auth Screen */
        .auth-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 80vh;
        }

        .auth-card {
            padding: 3rem 2rem;
            text-align: center;
            width: 100%;
            max-width: 450px;
            background: #fff;
        }

        .auth-card h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .auth-card p {
            font-weight: 600;
            margin-bottom: 2rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            border: 3px solid var(--main);
            border-radius: 6px;
            margin-bottom: 1.5rem;
            box-shadow: inset 4px 4px 0px rgba(0,0,0,0.05);
            outline: none;
        }

        .form-control:focus {
            background: var(--accent);
            box-shadow: 4px 4px 0px var(--main);
        }

        .btn-block {
            width: 100%;
        }

        .alert-error {
            background: var(--primary);
            color: white;
            padding: 1rem;
            border: 3px solid var(--main);
            font-weight: 700;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            box-shadow: 4px 4px 0px var(--main);
        }
        
        .alert-success {
            background: var(--secondary);
            color: white;
            padding: 1rem;
            border: 3px solid var(--main);
            font-weight: 700;
            border-radius: 6px;
            margin-bottom: 2rem;
            box-shadow: 4px 4px 0px var(--main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2.5rem;
        }

        .card {
            background: var(--surface);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-4px);
        }

        .card-img-wrapper {
            width: 100%;
            height: 250px;
            border-bottom: 3px solid var(--main);
            background: #f0f0f0;
            position: relative;
        }

        .card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            flex: 1;
        }

        .card-title {
            font-size: 0.9rem;
            font-weight: 700;
            background: var(--accent);
            padding: 0.5rem;
            border: 2px solid var(--main);
            border-radius: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-actions {
            display: flex;
            gap: 1rem;
            margin-top: auto;
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
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            background: var(--surface);
        }

        .empty-state h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
        }

        @media (max-width: 640px) {
            header {
                flex-direction: column;
                text-align: center;
            }
            header h1 {
                font-size: 2rem;
                justify-content: center;
            }
            .grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (!$isAuthenticated): ?>
    
    <div class="auth-wrapper">
        <div class="neo-box auth-card">
            <h2>Telemage Vault</h2>
            <p>Masukkan PIN untuk masuk ke galeri rahasia.</p>
            
            <?php if ($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="password" name="pin" class="form-control" placeholder="******" required autocomplete="off" autofocus>
                <button type="submit" class="btn btn-primary btn-block">
                    BUKA AKSES
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>

    <header>
        <h1>
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            TELEMAGE
        </h1>
        <a href="?logout=1" class="btn btn-light">
            KELUAR
        </a>
    </header>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
            Gambar berhasil dihapus!
        </div>
    <?php endif; ?>

    <div class="grid">
        <?php if (empty($images)): ?>
            <div class="neo-box empty-state">
                <h3>KOSONG</h3>
                <p>Kirimkan gambar ke bot Telegram untuk melihatnya di sini.</p>
            </div>
        <?php else: ?>
            <?php foreach ($images as $img): ?>
                <div class="neo-box card">
                    <div class="card-img-wrapper">
                        <!-- BYPASS .HTACCESS DENY FROM ALL VIA GET REQUEST -->
                        <img src="?img=<?php echo htmlspecialchars($img); ?>" alt="Upload" class="card-img" loading="lazy">
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($img); ?></div>
                        <div class="card-actions">
                            <a href="?img=<?php echo htmlspecialchars($img); ?>" target="_blank" class="btn btn-secondary">
                                OPEN
                            </a>
                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus gambar ini?');">
                                <input type="hidden" name="delete_img" value="<?php echo htmlspecialchars($img); ?>">
                                <button type="submit" class="btn btn-danger">
                                    HAPUS
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
