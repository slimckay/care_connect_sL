<?php
/**
 * Lightweight unread check for phone popup notifications
 * Returns new message / referral counts for the logged-in user
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/../db.php';

$userId = (int)$_SESSION['user_id'];
$role = strtolower($_SESSION['role'] ?? '');

$out = [
    'ok' => true,
    'role' => $role,
    'messages_unread' => 0,
    'latest_message' => null,
    'referrals_new' => 0,
    'latest_referral' => null,
];

try {
    // Unread chat messages
    if (in_array($role, ['patient', 'doctor', 'hospital'], true)) {
        if ($role === 'patient') {
            $sql = "
                SELECT m.id, m.message, m.created_at, m.conversation_id, u.name AS from_name
                FROM chat_messages m
                JOIN conversations c ON c.id = m.conversation_id
                JOIN users u ON u.id = m.sender_id
                WHERE c.patient_id = ? AND m.sender_id != ? AND m.is_read = 0
                ORDER BY m.id DESC
                LIMIT 1
            ";
            $cntSql = "
                SELECT COUNT(*) AS c FROM chat_messages m
                JOIN conversations c ON c.id = m.conversation_id
                WHERE c.patient_id = ? AND m.sender_id != ? AND m.is_read = 0
            ";
        } else {
            $sql = "
                SELECT m.id, m.message, m.created_at, m.conversation_id, u.name AS from_name
                FROM chat_messages m
                JOIN conversations c ON c.id = m.conversation_id
                JOIN users u ON u.id = m.sender_id
                WHERE c.provider_id = ? AND m.sender_id != ? AND m.is_read = 0
                ORDER BY m.id DESC
                LIMIT 1
            ";
            $cntSql = "
                SELECT COUNT(*) AS c FROM chat_messages m
                JOIN conversations c ON c.id = m.conversation_id
                WHERE c.provider_id = ? AND m.sender_id != ? AND m.is_read = 0
            ";
        }

        try {
            $c = $conn->prepare($cntSql);
            $c->execute([$userId, $userId]);
            $out['messages_unread'] = (int)($c->fetch()['c'] ?? 0);

            $m = $conn->prepare($sql);
            $m->execute([$userId, $userId]);
            $row = $m->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $out['latest_message'] = [
                    'id' => (int)$row['id'],
                    'from' => $row['from_name'],
                    'text' => mb_substr($row['message'], 0, 120),
                    'conversation_id' => (int)$row['conversation_id'],
                    'created_at' => $row['created_at'],
                ];
            }
        } catch (Exception $e) {
            // chat tables may not exist yet
        }
    }

    // New referrals for doctors / admin-ish provider view
    if (in_array($role, ['doctor', 'hospital'], true)) {
        try {
            // Assigned to this provider and still pending (or recently created)
            $r = $conn->prepare("
                SELECT id, patient_name, status, created_at
                FROM referrals
                WHERE assigned_to = ? AND status = 'pending'
                ORDER BY id DESC
                LIMIT 1
            ");
            $r->execute([$userId]);
            $ref = $r->fetch(PDO::FETCH_ASSOC);

            $rc = $conn->prepare("
                SELECT COUNT(*) AS c FROM referrals
                WHERE assigned_to = ? AND status = 'pending'
            ");
            $rc->execute([$userId]);
            $out['referrals_new'] = (int)($rc->fetch()['c'] ?? 0);

            if ($ref) {
                $out['latest_referral'] = [
                    'id' => (int)$ref['id'],
                    'patient_name' => $ref['patient_name'],
                    'status' => $ref['status'],
                    'created_at' => $ref['created_at'],
                ];
            }
        } catch (Exception $e) {
            // try open-pool pending as secondary signal for doctors
            try {
                $rc = $conn->prepare("SELECT COUNT(*) AS c FROM referrals WHERE status = 'pending' AND (assigned_to IS NULL OR assigned_to = 0)");
                $rc->execute();
                // don't overwrite assigned count if set; only use as soft signal when zero
                if ($out['referrals_new'] === 0) {
                    // leave 0 — open pool shouldn't spam all doctors
                }
            } catch (Exception $e2) {}
        }
    }

    // Patients: optional pending referral status updates could be added later
} catch (Exception $e) {
    error_log('notify-check: ' . $e->getMessage());
}

echo json_encode($out);
