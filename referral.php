<?php
/**
 * Referral Handler - Care Connect SL
 * Supports optional assigned_to (patient picks available doctor)
 * Sends SMS status fallback when phone/contact is present
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
$assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== ''
    ? (int)$_POST['assigned_to']
    : null;

if ($patient_name === '' || strlen($patient_name) < 2
    || $contact === ''
    || $location === '' || strlen($location) < 3
    || $condition === '' || strlen($condition) < 5
    || ($age !== null && ($age < 0 || $age > 150))
) {
    header('Location: pages/referral.html?error=validation');
    exit;
}

$assignedDoctorName = null;
if ($assigned_to) {
    try {
        $ds = $conn->prepare("SELECT id, name, role FROM users WHERE id = ? AND role IN ('doctor','hospital') LIMIT 1");
        $ds->execute([$assigned_to]);
        $doc = $ds->fetch();
        if (!$doc) {
            header('Location: pages/referral.html?error=doctor');
            exit;
        }
        $assignedDoctorName = $doc['name'];
        if ($preferred_clinic === '') {
            try {
                $ps = $conn->prepare("SELECT clinic_name FROM provider_profiles WHERE user_id = ? LIMIT 1");
                $ps->execute([$assigned_to]);
                $pr = $ps->fetch();
                if (!empty($pr['clinic_name'])) {
                    $preferred_clinic = $pr['clinic_name'];
                } else {
                    $preferred_clinic = $assignedDoctorName;
                }
            } catch (Exception $e) {
                $preferred_clinic = $assignedDoctorName;
            }
        }
    } catch (Exception $e) {
        header('Location: pages/referral.html?error=doctor');
        exit;
    }
}

function careConnectNotifyReferralSms(PDO $conn, string $phone, int $referralId): void
{
    if ($phone === '' || $referralId <= 0) return;
    try {
        require_once __DIR__ . '/sms_helper.php';
        $sms = new SmsHelper($conn);
        $sms->referralCreated($phone, $referralId, 'pending');
    } catch (Exception $e) {
        error_log('SMS on referral: ' . $e->getMessage());
    }
}

try {
    $cols = [];
    try {
        $colStmt = $conn->query('SHOW COLUMNS FROM referrals');
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cols[strtolower($c['Field'])] = $c['Field'];
        }
    } catch (Exception $e) {
        $cols = [];
    }

    $conditionCol = null;
    if (isset($cols['medical_condition'])) $conditionCol = $cols['medical_condition'];
    elseif (isset($cols['condition'])) $conditionCol = $cols['condition'];
    elseif (isset($cols['reason'])) $conditionCol = $cols['reason'];

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
        'assigned_to' => $assigned_to,
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

    if ($conditionCol !== null) {
        $fields[] = '`' . $conditionCol . '`';
        $values[] = '?';
        $params[] = $condition;
    }

    if (isset($cols['created_at'])) {
        $fields[] = '`' . $cols['created_at'] . '`';
        $values[] = 'NOW()';
    }

    if (empty($fields) || $conditionCol === null) {
        $sql = "INSERT INTO referrals (patient_name, contact, location, medical_condition, status)
                VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$patient_name, $contact, $location, $condition]);
    } else {
        $sql = 'INSERT INTO referrals (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    }

    $referralId = (int)$conn->lastInsertId();

    if ($assigned_to) {
        try {
            $conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                VALUES (?, 'referral_assigned', 'New referral for you', ?, ?, 0, NOW())
            ")->execute([
                $assigned_to,
                'A new referral for ' . $patient_name . ' was assigned to you.',
                'dashboard/provider-referrals.php'
            ]);
        } catch (Exception $e) {}
    }

    try {
        $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        $msg = $assignedDoctorName
            ? 'New referral for ' . $patient_name . ' assigned to ' . $assignedDoctorName . '.'
            : 'New referral for ' . $patient_name . ' (open pool).';
        $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                             VALUES (?, 'referral_new', 'New referral submitted', ?, 'admin/manage-referrals.php', 0, NOW())");
        foreach ($admins as $a) {
            $n->execute([(int)$a['id'], $msg]);
        }
    } catch (Exception $e) {}

    careConnectNotifyReferralSms($conn, $contact, $referralId);

    $q = 'sent=1&ref=' . $referralId;
    if ($assigned_to) $q .= '&assigned=1';
    header('Location: pages/referral.html?' . $q);
    exit;

} catch (PDOException $e) {
    error_log('Referral PDO error: ' . $e->getMessage());

    $attempts = [
        "INSERT INTO referrals (patient_name, age, contact, location, medical_condition, status, assigned_to) VALUES (?, ?, ?, ?, ?, 'pending', ?)",
        "INSERT INTO referrals (patient_name, age, contact, location, `condition`, status, assigned_to) VALUES (?, ?, ?, ?, ?, 'pending', ?)",
        "INSERT INTO referrals (patient_name, age, contact, location, medical_condition, status) VALUES (?, ?, ?, ?, ?, 'pending')",
        "INSERT INTO referrals (patient_name, age, contact, location, `condition`, status) VALUES (?, ?, ?, ?, ?, 'pending')",
    ];

    foreach ($attempts as $i => $sql) {
        try {
            $stmt = $conn->prepare($sql);
            if ($i < 2) {
                $stmt->execute([$patient_name, $age, $contact, $location, $condition, $assigned_to]);
            } else {
                $stmt->execute([$patient_name, $age, $contact, $location, $condition]);
            }
            $referralId = (int)$conn->lastInsertId();
            careConnectNotifyReferralSms($conn, $contact, $referralId);
            $q = 'sent=1&ref=' . $referralId . ($assigned_to ? '&assigned=1' : '');
            header('Location: pages/referral.html?' . $q);
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
