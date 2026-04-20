<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$_SESSION = [];
session_destroy();
header('Location: /console/login.php'); exit;
