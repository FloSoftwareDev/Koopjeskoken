<?php
require_once __DIR__ . '/api/helpers.php';

startSecureSession();

if (empty($_SESSION['user_id'])) {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    // Vanuit /admin/dashboard.php moeten we één map omhoog
    if (basename($base) === 'admin') {
        $base = dirname($base);
    }
    if ($base === '' || $base === '.' || $base === '/' || $base === '\\') {
        $base = '';
    }
    header('Location: ' . $base . '/login.php');
    exit;
}
