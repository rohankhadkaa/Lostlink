<?php
require_once __DIR__ . "/mailer.php";

$ok = send_email("YOUR_GMAIL@gmail.com", "Test", "VU LostLink SMTP Test", "<b>Email sending works!</b>");
echo $ok ? "EMAIL SENT ✅" : "FAILED ❌ check mail_fallback.log";