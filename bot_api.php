<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_user();

header("Content-Type: application/json; charset=utf-8");

function bot_reply(string $key): string {
    switch ($key) {
        case "how_upload":
            return "Go to your Dashboard → click 'Upload Item' → fill in item name + description → choose Lost/Found → submit.";
        case "how_verification":
            return "After you upload, Admin may send verification questions. Answer them in your Dashboard. Then Admin will approve or reject.";
        case "why_otp":
            return "OTP adds security. Each login sends a one-time code to your email to confirm it’s really you.";
        case "approved_lost":
            return "If your LOST item is approved: you will receive a notification/email. Please collect your item at Level G (Uni Building).";
        case "approved_found":
            return "If your FOUND item is approved: you will receive a notification/email. Please bring the item to Level G (Uni Building) and hand it to staff.";
        case "rejected":
            return "If rejected, the verification details didn’t match. If you believe this is incorrect, contact support.";
        case "browse_privacy":
            return "Browse shows limited info to prevent theft. Full details are only visible to the owner + admin during verification.";
        case "other":
        default:
            return "For other queries, contact: lostlink.vu@gmail.com";
    }
}

$key = strtolower(trim($_POST["q"] ?? ""));
echo json_encode([
    "ok" => true,
    "reply" => bot_reply($key)
]);