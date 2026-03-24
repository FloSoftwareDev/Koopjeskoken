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

// Rate-limit registration attempts by IP
rateLimit('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 300);

$data = getJsonBody();
requireFields($data, ['username', 'email', 'password']);

$username = trim($data['username']);
$email    = trim($data['email']);
$password = $data['password'];

// ── Validation ────────────────────────────────────────────────────────────────
if (strlen($username) < 3 || strlen($username) > 30) {
    http_response_code(422);
    echo json_encode(['error' => 'Username must be between 3 and 30 characters']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    http_response_code(422);
    echo json_encode(['error' => 'Username may only contain letters, numbers, and underscores']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

$conn = getDb();

// Check if username or email already exists
$stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->bind_param('ss', $username, $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Username or email already in use']);
    exit;
}
$stmt->close();

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $conn->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
$role = 'user';
$stmt->bind_param('ssss', $username, $email, $hash, $role);
$stmt->execute();
$stmt->close();

http_response_code(201);
echo json_encode(['success' => true, 'message' => 'Account created successfully']);