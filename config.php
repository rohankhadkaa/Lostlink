<?php
// =============================
// VU LostLink - Global Config
// =============================

// ---------- ENVIRONMENT ----------
date_default_timezone_set("Australia/Sydney");

// Turn ON for development, OFF for production
ini_set("display_errors", 1);
error_reporting(E_ALL);

// ---------- SESSION ----------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- DATABASE ----------
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "");          // XAMPP's MySQL root has no password by default
define("DB_NAME", "lost_found_db");

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ---------- OTP SETTINGS (FIXES YOUR ERROR) ----------
if (!defined("OTP_TTL_MINUTES")) {
    define("OTP_TTL_MINUTES", 5); // OTP valid for 5 minutes
}
if (!defined("OTP_RESEND_SECONDS")) {
    define("OTP_RESEND_SECONDS", 30); // wait 30 seconds before resend
}

// ---------- EMAIL (SMTP via Gmail) ----------
// IMPORTANT: Use a Gmail App Password (NOT your real Gmail password)
// Requires Google 2-Step Verification enabled

define("SMTP_HOST", "smtp.gmail.com");
define("SMTP_PORT", 587); // Use 465 if 587 blocked
define("SMTP_USER", "lostlink.vu@gmail.com");        // your sender Gmail
define("SMTP_PASS", "vvzc filq vqsj vzjn");          // Gmail App Password
define("SMTP_FROM_EMAIL", "lostlink.vu@gmail.com");  // MUST match SMTP_USER for Gmail
define("SMTP_FROM_NAME", "VU LostLink");

// ---------- MAIL FALLBACK LOG ----------
// If you want to disable fallback logging completely, set to empty string ""
define("MAIL_FALLBACK_LOG", __DIR__ . "/mail_fallback.log");

// ---------- OPTIONAL: LOCALHOST CHECK ----------
function is_localhost(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($ip, ['127.0.0.1', '::1'], true);
}
