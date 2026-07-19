<?php
/**
 * Database Configuration - PRODUCTION (Render + Aiven)
 * Retries on cold start so brief wake-up blips don't kill the page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'careconnect_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
    PDO::ATTR_TIMEOUT            => 8,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

$conn = null;
$lastError = '';
$attempts = 3;

for ($i = 1; $i <= $attempts; $i++) {
    try {
        $conn = new PDO($dsn, $user, $pass, $options);
        break;
    } catch (PDOException $e) {
        $lastError = $e->getMessage();
        error_log("Database Connection Error (attempt $i/$attempts): " . $lastError);
        if ($i < $attempts) {
            usleep(700000); // 0.7s pause between retries (helps cold start)
        }
    }
}

if (!$conn) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 5');
    // Auto-refresh so users don't have to manually retry during wake-up
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta http-equiv="refresh" content="5">';
    echo '<title>Waking up — Care Connect SL</title>';
    echo '<style>body{font-family:system-ui,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;background:#F4F7FB;color:#0F1C3A;text-align:center;padding:24px}';
    echo '.box{max-width:420px}.spinner{width:40px;height:40px;border:3px solid #E5E7EB;border-top-color:#1EB53A;border-radius:50%;margin:0 auto 16px;animation:spin 0.8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}';
    echo 'h1{font-size:1.25rem;margin:0 0 8px}p{color:#64748B;line-height:1.5;margin:0}</style></head><body>';
    echo '<div class="box"><div class="spinner"></div>';
    echo '<h1>Care Connect is waking up…</h1>';
    echo '<p>Free hosting sleeps when idle. This page will retry automatically in a few seconds.</p>';
    echo '</div></body></html>';
    exit;
}

function dbQuery($sql, $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function dbFetchOne($sql, $params = []) {
    return dbQuery($sql, $params)->fetch();
}

function dbFetchAll($sql, $params = []) {
    return dbQuery($sql, $params)->fetchAll();
}

function dbInsert($sql, $params = []) {
    global $conn;
    dbQuery($sql, $params);
    return $conn->lastInsertId();
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_null($data)) return '';
        $data = trim($data);
        $data = stripslashes($data);
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function checkRateLimit($key, $limit = 5, $window = 300) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $sessionKey = 'rate_limit_' . $key;
    $timeKey = 'rate_limit_time_' . $key;
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = 1;
        $_SESSION[$timeKey] = time();
        return true;
    }
    if (time() - $_SESSION[$timeKey] > $window) {
        $_SESSION[$sessionKey] = 1;
        $_SESSION[$timeKey] = time();
        return true;
    }
    if ($_SESSION[$sessionKey] >= $limit) return false;
    $_SESSION[$sessionKey]++;
    return true;
}
