<?php
/**
 * Referral Handler - Care Connect SL
 * Schema-resilient: works whether DB uses `condition` or `medical_condition`
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pages/referral.html');
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (function_exists('checkRateLimit') && !checkRateLimit('referral_' . $ip, 10, 3600)) {
    header('Location: pages/referral.html?error=too_many');
    exit;
}

$patient_name = function_exists('sanitizeInput')
    ? sanitizeInput($_POST['patient_name'] ?? '')
    : trim(htmlspecialchars($_POST['patient_name'] ?? '', ENT_QUOTES, 'UTF-8'));

$age = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null;
$contact = function_exists('sanitizeInput')
    ? sanitizeInput($_POST['contact'] ?? '')
    : trim(htmlspecialchars($_POST['contact'] ?? '', ENT_QUOTES, 'UTF-8'));
$location = function_exists('sanitizeInput')
    ? sanitizeInput($_POST['location'] ?? '')
    : trim(htmlspecialchars($_POST['location'] ?? '', ENT_QUOTES, 'UTF-8'));
$preferred_clinic = function_exists('sanitizeInput')
    ? sanitizeInput($_POST['preferred_clinic'] ?? '')
    : trim(htmlspecialchars($_POST['preferred_clinic'] ?? '', ENT_QUOTES, 'UTF-8'));
$condition = function_exists('sanitizeInput')
    ? sanitizeInput($_POST['condition'] ?? '')
    : trim(htmlspecialchars($_POST['condition'] ?? '', ENT_QUOTES, 'UTF-8'));
$referrer = function_exists('sanitizeInput')
    ? sanitizeInput($_POST['referrer'] ?? 'self')
    : 'self';
$user_id = $_SESSION['user_id'] ?? null;

// Basic validation
if ($patient_name === '' || strlen($patient_name) < 2
    || $contact === ''
    || $location === '' || strlen($location) < 3
    || $condition === '' || strlen($condition) < 5
    || ($age !== null && ($age < 0 || $age > 150))
) {
    header('Location: pages/referral.html?error=validation');
    exit;
}

try {
    // Discover actual columns on referrals table
    $cols = [];
    try {
        $colStmt = $conn->query('SHOW COLUMNS FROM referrals');
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cols[strtolower($c['Field'])] = $c['Field'];
        }
    } catch (Exception $e) {
        $cols = [];
    }

    // Pick the correct medical text column
    $conditionCol = null;
    if (isset($cols['medical_condition'])) {
        $conditionCol = $cols['medical_condition'];
    } elseif (isset($cols['condition'])) {
        $conditionCol = $cols['condition'];
    } elseif (isset($cols['reason'])) {
        $conditionCol = $cols['reason'];
    }

    // Build insert dynamically from available columns
    $fields = [];
    $values = [];
    $params = [];

    $map = [
        'patient_name' => $patient_name,
        'age' => $age,
        'contact' => $contact,
        'location' => $location,
        'preferred_clinic' => ($preferred_clinic !== '' ? $preferred_clinic : null),
        'referrer' => $referrer,
        'user_id' => $user_id,
        'status' => 'pending',
        'ip_address' => $ip,
    ];

    foreach ($map as $key => $val) {
        if (isset($cols[$key])) {
            $fields[] = '`' . $cols[$key] . '`';
            $values[] = '?';
            $params[] = $val;
        }
    }

    // Condition / medical_condition column
    if ($conditionCol !== null) {
        $fields[] = '`' . $conditionCol . '`';
        $values[] = '?';
        $params[] = $condition;
    }

    // created_at if present and no default relied on
    if (isset($cols['created_at'])) {
        $fields[] = '`' . $cols['created_at'] . '`';
        $values[] = 'NOW()';
    }

    if (empty($fields) || $conditionCol === null) {
        // Absolute fallback – minimal known schema
        $sql = "INSERT INTO referrals (patient_name, contact, location, medical_condition, status)
                VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$patient_name, $contact, $location, $condition]);
    } else {
        $sql = 'INSERT INTO referrals (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    }

    $referralId = $conn->lastInsertId();
    error_log('New referral submitted: ID ' . $referralId . ' for ' . $patient_name);

    header('Location: pages/referral.html?sent=1');
    exit;

} catch (PDOException $e) {
    error_log('Referral PDO error: ' . $e->getMessage());

    // Last-resort minimal insert attempts
    $attempts = [
        "INSERT INTO referrals (patient_name, age, contact, location, medical_condition, status) VALUES (?, ?, ?, ?, ?, 'pending')",
        "INSERT INTO referrals (patient_name, age, contact, location, `condition`, status) VALUES (?, ?, ?, ?, ?, 'pending')",
        "INSERT INTO referrals (patient_name, contact, location, medical_condition) VALUES (?, ?, ?, ?)",
        "INSERT INTO referrals (patient_name, contact, location, `condition`) VALUES (?, ?, ?, ?)",
    ];

    foreach ($attempts as $i => $sql) {
        try {
            $stmt = $conn->prepare($sql);
            if ($i < 2) {
                $stmt->execute([$patient_name, $age, $contact, $location, $condition]);
            } else {
                $stmt->execute([$patient_name, $contact, $location, $condition]);
            }
            header('Location: pages/referral.html?sent=1');
            exit;
        } catch (Exception $inner) {
            error_log('Referral fallback ' . $i . ' failed: ' . $inner->getMessage());
        }
    }

    header('Location: pages/referral.html?error=server');
    exit;
} catch (Exception $e) {
    error_log('Referral error: ' . $e->getMessage());
    header('Location: pages/referral.html?error=server');
    exit;
}
