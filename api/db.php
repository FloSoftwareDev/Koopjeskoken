<?php
// Ideally these come from environment variables or a config file outside the web root.
// For local dev you can keep them here, but never commit real credentials.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'koopjeskoken');

function getDb(): mysqli {
    static $conn = null;

    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            // Log the real error, return a safe message
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database unavailable']);
            exit;
        }
    }

    return $conn;
}