<?php
/*
|--------------------------------------------------------------------------
| notifications.php
|--------------------------------------------------------------------------
| Helper functions for system notifications.
| Requires: config.php (for $conn)
| Table: notifications
|
| Table structure expected:
|
| CREATE TABLE notifications (
|   id INT AUTO_INCREMENT PRIMARY KEY,
|   user_id INT NOT NULL,
|   title VARCHAR(255) NOT NULL,
|   message TEXT NOT NULL,
|   is_read TINYINT(1) NOT NULL DEFAULT 0,
|   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
| );
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Add notification
 */
function add_notification(int $user_id, string $title, string $message): bool {
    global $conn;

    if (!isset($conn)) return false;

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, is_read)
        VALUES (?, ?, ?, 0)
    ");

    if (!$stmt) return false;

    $stmt->bind_param("iss", $user_id, $title, $message);
    return $stmt->execute();
}

/**
 * Get unread notification count
 */
function unread_count_for_user(int $user_id): int {
    global $conn;

    if (!isset($conn)) return 0;

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM notifications
        WHERE user_id=? AND is_read=0
    ");

    if (!$stmt) return 0;

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return (int)($result["total"] ?? 0);
}

/**
 * Fetch all notifications for user
 */
function get_notifications_for_user(int $user_id) {
    global $conn;

    if (!isset($conn)) return false;

    $stmt = $conn->prepare("
        SELECT id, title, message, is_read, created_at
        FROM notifications
        WHERE user_id=?
        ORDER BY created_at DESC
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    return $stmt->get_result();
}

/**
 * Mark single notification as read
 */
function mark_notification_read(int $notification_id, int $user_id): bool {
    global $conn;

    if (!isset($conn)) return false;

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read=1
        WHERE id=? AND user_id=?
    ");

    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

/**
 * Mark all notifications as read
 */
function mark_all_notifications_read(int $user_id): bool {
    global $conn;

    if (!isset($conn)) return false;

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read=1
        WHERE user_id=?
    ");

    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Delete single notification
 */
function delete_notification(int $notification_id, int $user_id): bool {
    global $conn;

    if (!isset($conn)) return false;

    $stmt = $conn->prepare("
        DELETE FROM notifications
        WHERE id=? AND user_id=?
    ");

    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

/**
 * Delete all notifications for user
 */
function delete_all_notifications(int $user_id): bool {
    global $conn;

    if (!isset($conn)) return false;

    $stmt = $conn->prepare("
        DELETE FROM notifications
        WHERE user_id=?
    ");

    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}