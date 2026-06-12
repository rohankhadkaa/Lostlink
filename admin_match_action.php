<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_admin();

$action = strtolower(trim($_GET['action'] ?? ''));
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0 || !in_array($action, ['dismiss','confirm'], true)) {
    header("Location: admin_dashboard.php");
    exit;
}

$newStatus = ($action === 'dismiss') ? 'dismissed' : 'confirmed';

$st = $conn->prepare("UPDATE ai_matches SET status=? WHERE id=?");
$st->bind_param("si", $newStatus, $id);
$st->execute();

header("Location: admin_dashboard.php");
exit;