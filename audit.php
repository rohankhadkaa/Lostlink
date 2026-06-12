<?php
// audit.php — structured audit logging for claims (Feature 10)
if (!function_exists("log_audit")) {
    function log_audit(mysqli $conn, int $claim_id, ?int $user_id, string $action, ?string $detail = null): void {
        $st = $conn->prepare("INSERT INTO claim_audit (claim_id, user_id, action, detail) VALUES (?,?,?,?)");
        if ($st) {
            $st->bind_param("iiss", $claim_id, $user_id, $action, $detail);
            $st->execute();
        }
    }
}