<?php
/**
 * Admin badge "seen" tracking — like chat unread.
 * Opening a section clears its badge; new items after that accumulate again.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_seen']) || !is_array($_SESSION['admin_seen'])) {
    $_SESSION['admin_seen'] = [];
}

/** Mark a section as viewed right now (clears badge). */
function admin_mark_seen(string $key): void
{
    $_SESSION['admin_seen'][$key] = date('Y-m-d H:i:s');
}

/** Last time admin opened this section (null = never). */
function admin_last_seen(string $key): ?string
{
    $v = $_SESSION['admin_seen'][$key] ?? null;
    return is_string($v) && $v !== '' ? $v : null;
}

/**
 * Messages: mark every contact message still "new" as "read".
 * Call when opening manage-messages.php so the badge drops to 0.
 */
function admin_clear_new_messages(PDO $conn): void
{
    try {
        $conn->exec("UPDATE contact_messages SET status = 'read', updated_at = NOW() WHERE status = 'new'");
    } catch (Exception $e) {
        try {
            $conn->exec("UPDATE contact_messages SET status = 'read' WHERE status = 'new'");
        } catch (Exception $e2) {}
    }
    admin_mark_seen('messages');
}

/**
 * Notifications: mark all unread for this admin as read.
 */
function admin_clear_notifications(PDO $conn, int $adminId): void
{
    if ($adminId <= 0) {
        admin_mark_seen('notifications');
        return;
    }
    try {
        $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)")
             ->execute([$adminId]);
    } catch (Exception $e) {}
    admin_mark_seen('notifications');
}
