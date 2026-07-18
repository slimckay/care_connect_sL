<?php
/**
 * List available doctors/clinics for referral assignment
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../db.php';

try {
    // Prefer verified + accepting patients when columns exist
    try {
        $rows = $conn->query("
            SELECT u.id, u.name, u.role,
                   p.specialty, p.clinic_name, p.clinic_address,
                   p.is_accepting_patients, p.verification_status
            FROM users u
            LEFT JOIN provider_profiles p ON p.user_id = u.id
            WHERE u.role IN ('doctor', 'hospital')
              AND (u.status = 'active' OR u.status IS NULL)
              AND (p.is_accepting_patients IS NULL OR p.is_accepting_patients = 1)
              AND (p.verification_status IS NULL OR p.verification_status = 'verified' OR p.verification_status = 'pending')
            ORDER BY
              CASE WHEN p.verification_status = 'verified' THEN 0 ELSE 1 END,
              u.name ASC
            LIMIT 80
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $rows = $conn->query("
            SELECT u.id, u.name, u.role,
                   NULL AS specialty, NULL AS clinic_name, NULL AS clinic_address,
                   1 AS is_accepting_patients, NULL AS verification_status
            FROM users u
            WHERE u.role IN ('doctor', 'hospital')
            ORDER BY u.name ASC
            LIMIT 80
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    $providers = array_map(function ($r) {
        $label = $r['name'];
        $bits = [];
        if (!empty($r['specialty'])) $bits[] = $r['specialty'];
        if (!empty($r['clinic_name'])) $bits[] = $r['clinic_name'];
        if (!empty($r['role'])) $bits[] = ucfirst($r['role']);
        if ($bits) $label .= ' — ' . implode(' · ', $bits);
        return [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'label' => $label,
            'specialty' => $r['specialty'] ?? '',
            'clinic_name' => $r['clinic_name'] ?? '',
            'role' => $r['role'] ?? '',
            'verified' => (($r['verification_status'] ?? '') === 'verified'),
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'providers' => $providers]);
} catch (Exception $e) {
    error_log('available-providers: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'providers' => [], 'error' => 'Could not load providers']);
}
