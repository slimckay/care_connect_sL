<?php
/**
 * Database Configuration - PRODUCTION (Render + Aiven)
 * Reads credentials from environment variables
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// READ FROM ENVIRONMENT VARIABLES (set in Render)
// ============================================
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'careconnect_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// Build DSN with port
$dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

// PDO options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
    // Uncomment for debugging (remove in production)
    // error_log("✅ Database connected successfully");
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("<h2>Service Unavailable</h2><p>We're experiencing technical difficulties. Please try again later.</p>");
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Execute a prepared statement and return the statement object
 */
function dbQuery($sql, $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single record
 */
function dbFetchOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Fetch all records
 */
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert a record and return the last insert ID
 */
function dbInsert($sql, $params = []) {
    global $conn;
    $stmt = dbQuery($sql, $params);
    return $conn->lastInsertId();
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_null($data)) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate a CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token
 */
function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate limiting – prevent brute force
 */
function checkRateLimit($key, $limit = 5, $window = 300) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionKey = 'rate_limit_' . $key;
    $timeKey = 'rate_limit_time_' . $key;
    
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = 1;
        $_SESSION[$timeKey] = time();
        return true;
    }
    
    $timeDiff = time() - $_SESSION[$timeKey];
    
    if ($timeDiff > $window) {
        $_SESSION[$sessionKey] = 1;
        $_SESSION[$timeKey] = time();
        return true;
    }
    
    if ($_SESSION[$sessionKey] >= $limit) {
        return false;
    }
    
    $_SESSION[$sessionKey]++;
    return true;
}
?>
