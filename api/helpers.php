<?php
/**
 * helpers.php — shared utilities for every API endpoint
 */

// ── Headers ──────────────────────────────────────────────────────────────────

function getAllowedOrigins(): array {
    $env = getenv('CORS_ALLOWED_ORIGINS');
    if ($env !== false && $env !== '') {
        return array_map('trim', explode(',', $env));
    }
    return [
        'http://localhost',
        'http://127.0.0.1',
        'http://127.0.0.1:5500',
        'http://localhost:5500',
    ];
}

function setCorsHeaders(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, getAllowedOrigins(), true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
}

function setJsonHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
}

function handleOptions(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Session ───────────────────────────────────────────────────────────────────

const IDLE_TIMEOUT = 30 * 60; // 30 minutes of inactivity expires the session

function startSecureSession(bool $rememberMe = false): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = $rememberMe ? 30 * 24 * 60 * 60 : 0;
        // Auto-detect HTTPS so cookies are marked Secure in production.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? null) == 443)
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > IDLE_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_active'] = time();
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? json_decode(file_get_contents('php://input'), true)['csrf_token']
          ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

// ── Input helpers ─────────────────────────────────────────────────────────────

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }
    return $data;
}

function requireFields(array $data, array $fields): void {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            exit;
        }
    }
}

// ── Rate limiting (file-based, good enough for small projects) ────────────────

function rateLimit(string $key, int $maxAttempts = 10, int $windowSeconds = 60): void {
    $dir  = sys_get_temp_dir() . '/rl_koopjeskoken';
    if (!is_dir($dir)) mkdir($dir, 0700, true);

    $file = $dir . '/' . hash('sha256', $key) . '.json';
    $now  = time();
    $data = ['count' => 0, 'window_start' => $now];

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($now - $data['window_start'] > $windowSeconds) {
            $data = ['count' => 0, 'window_start' => $now];
        }
    }

    $data['count']++;
    file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $maxAttempts) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests, please try again later']);
        exit;
    }
}

// ── Auth guard ────────────────────────────────────────────────────────────────

function requireAuth(): void {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
}

function requireAdmin(): void {
    requireAuth();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized']);
        exit;
    }
}