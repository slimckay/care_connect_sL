<?php
/**
 * Database Configuration & Connection
 * Care Connect SL - Secure Database Setup
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'careconnect_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // Set your password for production

// Connection options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    // First, connect without database to create it if needed
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        $options
    );
    
    // Check if database exists, create if not
    $checkDb = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    if ($checkDb->rowCount() === 0) {
        $conn->exec("CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->exec("USE " . DB_NAME);
        
        // Create tables
        $conn->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('patient', 'doctor', 'hospital', 'admin') DEFAULT 'patient',
                status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
                email_verified BOOLEAN DEFAULT FALSE,
                verification_token VARCHAR(64) NULL,
                reset_token VARCHAR(64) NULL,
                reset_token_expires DATETIME NULL,
                last_login DATETIME NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $conn->exec("
            CREATE TABLE IF NOT EXISTS provider_profiles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                specialty VARCHAR(100) NULL,
                qualifications TEXT NULL,
                experience_years INT DEFAULT 0,
                clinic_name VARCHAR(200) NULL,
                clinic_address VARCHAR(255) NULL,
                clinic_phone VARCHAR(50) NULL,
                consultation_fee DECIMAL(10,2) DEFAULT 0,
                is_accepting_patients BOOLEAN DEFAULT TRUE,
                national_id VARCHAR(50) NULL,
                medical_license VARCHAR(50) NULL,
                bio TEXT NULL,
                profile_photo VARCHAR(255) NULL,
                id_photo VARCHAR(255) NULL,
                license_photo VARCHAR(255) NULL,
                verification_status ENUM('pending', 'verified', 'rejected', 'not_submitted') DEFAULT 'not_submitted',
                verified_at DATETIME NULL,
                verified_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $conn->exec("
            CREATE TABLE IF NOT EXISTS referrals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                patient_name VARCHAR(100) NOT NULL,
                age INT NULL,
                contact VARCHAR(100) NOT NULL,
                location VARCHAR(200) NOT NULL,
                preferred_clinic VARCHAR(100) NULL,
                medical_condition TEXT NOT NULL,
                referrer VARCHAR(50) DEFAULT 'self',
                assigned_to INT NULL,
                status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
                ip_address VARCHAR(45) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $conn->exec("
            CREATE TABLE IF NOT EXISTS ai_conversations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                user_message TEXT NOT NULL,
                ai_response TEXT NOT NULL,
                source VARCHAR(50) DEFAULT 'api',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        
        $conn->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create admin user if none exists
        $checkAdmin = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        if ($checkAdmin->rowCount() === 0) {
            $hashed = password_hash('Admin123!', PASSWORD_DEFAULT);
            $conn->exec("
                INSERT INTO users (name, email, password, role, status, email_verified) 
                VALUES ('System Admin', 'admin@careconnect.sl', '$hashed', 'admin', 'active', 1)
            ");
        }
    } else {
        // Database exists, use it
        $conn->exec("USE " . DB_NAME);
    }
    
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("<h2>Service Unavailable</h2><p>We're experiencing technical difficulties. Please try again later.</p>");
}

/**
 * Helper function to execute prepared statements
 */
function dbQuery($sql, $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Get single record
 */
function dbFetchOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Get multiple records
 */
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert record and return last insert ID
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
 * Generate CSRF token
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
 * Verify CSRF token
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
 * Rate limiting - prevent brute force
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