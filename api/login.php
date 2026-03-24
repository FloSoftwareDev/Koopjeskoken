<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

setCorsHeaders();
setJsonHeaders();
handleOptions();
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate-limit login attempts per IP
rateLimit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 60);

$data = getJsonBody();
requireFields($data, ['username', 'password']);

$username = trim($data['username']);
$password = $data['password'];

$conn = getDb();
$stmt = $conn->prepare('SELECT id, password, role FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

// Always run password_verify even when the user doesn't exist to prevent
// timing-based username enumeration.
$dummyHash = '$2y$12$invalidhashinvalidhashinvalidhashi';
$hash = $user['password'] ?? $dummyHash;

if (!$user || !password_verify($password, $hash)) {
    // Generic message — don't reveal whether username or password was wrong
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    exit;
}

// Handle remember me functionality
$rememberMe = $data['remember_me'] ?? false;
startSecureSession($rememberMe);

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

$_SESSION['user_id']  = $user['id'];
$_SESSION['role']     = $user['role'];
$_SESSION['username'] = $username;

// Issue a CSRF token now that the user is authenticated
$csrfToken = generateCsrfToken();

echo json_encode([
    'success'    => true,
    'role'       => $user['role'],
    'csrf_token' => $csrfToken,
]);