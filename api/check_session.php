<?php
require_once __DIR__ . '/helpers.php';

setCorsHeaders();
setJsonHeaders();
handleOptions();
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    echo json_encode(['logged_in' => false]);
    exit;
}

// Refresh the CSRF token for the client
$csrfToken = generateCsrfToken();

echo json_encode([
    'logged_in'  => true,
    'user_id'    => $_SESSION['user_id'],
    'username'   => $_SESSION['username'] ?? null,
    'role'       => $_SESSION['role'],
    'csrf_token' => $csrfToken,
]);