<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'u82149p138748_koopjeskoken');
define('DB_PASS', 'j2JTRwpM4zbNpYREpLU6');
define('DB_NAME', 'u82149p138748_koopjeskoken');

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