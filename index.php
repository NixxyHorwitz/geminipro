<?php
// Simple redirect page to avoid URL shortener flags on the main domain
require_once __DIR__ . '/bootstrap.php';
use App\Config;

$useLoadingScreen = Config::get('landing_loading_screen', '1');

if ($useLoadingScreen == '0') {
    // Direct PHP redirect
    header("Location: /plan");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loading...</title>
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="2;url=/plan">
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #5f6368;
        }
        .loader-container {
            text-align: center;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(26, 115, 232, 0.2);
            border-radius: 50%;
            border-top-color: #1a73e8;
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loader-container">
        <div class="spinner"></div>
        <div>Connecting securely...</div>
    </div>
    <script>
        // Redirect after a short delay to allow the page to render and pass basic bot checks
        setTimeout(function() {
            window.location.replace('/plan');
        }, 1500);
    </script>
</body>
</html>
