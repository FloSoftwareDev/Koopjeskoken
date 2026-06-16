<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

setCorsHeaders();
setJsonHeaders();
handleOptions();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

rateLimit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 60);

$data = getJsonBody();
requireFields($data, ['username', 'password']);

$username   = trim($data['username']);
$password   = $data['password'];
$rememberMe = $data['remember_me'] ?? false;

startSecureSession($rememberMe);

$usernameMatches = hash_equals(AUTH_USERNAME, $username);
$passwordMatches = password_verify($password, AUTH_PASSWORD_HASH);

if (!$usernameMatches || !$passwordMatches) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id']  = 1;
$_SESSION['role']     = AUTH_ROLE;
$_SESSION['username'] = AUTH_USERNAME;

$csrfToken = generateCsrfToken();

echo json_encode([
    'success'    => true,
    'role'       => AUTH_ROLE,
    'csrf_token' => $csrfToken,
]);
