<?php
// chatbot.php

// IMPORTANT: no whitespace before this file starts
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name("VULOSTLINKSESSID");
    session_start();
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_user(); // users only

// Initialize chat history
if (!isset($_SESSION["chat_history"]) || !is_array($_SESSION["chat_history"])) {
    $_SESSION["chat_history"] = [];

    // Welcome message
    $_SESSION["chat_history"][] = [
        "from" => "bot",
        "text" => "Hi! I’m the VU LostLink Help Bot 👋 Select a question below to get an instant answer."
    ];
}

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
            return "For other queries, please contact us at: lostlink.vu@gmail.com";
    }
}

// Clear chat
if (isset($_GET["clear"]) && $_GET["clear"] === "1") {
    unset($_SESSION["chat_history"]);
    header("Location: chatbot.php");
    exit;
}

// Handle user selecting a question (via GET so it works without JS)
if (!empty($_GET["q"])) {
    $q = trim($_GET["q"]);

    // Map keys to user-visible message
    $labels = [
        "how_upload"       => "How do I upload a lost/found item?",
        "how_verification" => "How does verification work?",
        "why_otp"          => "Why do I need OTP to log in?",
        "approved_lost"    => "My LOST item got approved — what next?",
        "approved_found"   => "My FOUND item got approved — what next?",
        "rejected"         => "My request was rejected — what can I do?",
        "browse_privacy"   => "Why does Browse show limited info?",
        "other"            => "I have a different query",
    ];

    $userText = $labels[$q] ?? "I have a different query";

    // Add user message
    $_SESSION["chat_history"][] = ["from" => "user", "text" => $userText];

    // Add bot reply
    $_SESSION["chat_history"][] = ["from" => "bot", "text" => bot_reply($q)];

    // Prevent resubmission on refresh
    header("Location: chatbot.php");
    exit;
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4" style="max-width:900px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="section-title m-0">VU LostLink Chatbot</h2>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-danger" href="chatbot.php?clear=1"
         onclick="return confirm('Clear chat history?');">Clear Chat</a>
      <a class="btn btn-outline-primary" href="user_dashboard.php">Back</a>
    </div>
  </div>

  <div class="card mt-3" style="height:520px; display:flex; flex-direction:column;">
    <!-- Chat window -->
    <div class="chat-body" style="flex:1; overflow:auto;">
      <?php foreach ($_SESSION["chat_history"] as $msg): ?>
        <?php if (($msg["from"] ?? "") === "user"): ?>
          <div class="chat-msg user"><?= htmlspecialchars($msg["text"] ?? "") ?></div>
        <?php else: ?>
          <div class="chat-msg bot"><?= htmlspecialchars($msg["text"] ?? "") ?></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- Quick question buttons -->
    <div class="mt-3">
      <div class="text-muted small mb-2">Quick questions:</div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-primary" href="chatbot.php?q=how_upload">Upload item</a>
        <a class="btn btn-sm btn-outline-primary" href="chatbot.php?q=how_verification">Verification</a>
        <a class="btn btn-sm btn-outline-primary" href="chatbot.php?q=why_otp">OTP login</a>
        <a class="btn btn-sm btn-outline-primary" href="chatbot.php?q=approved_lost">Approved (Lost)</a>
        <a class="btn btn-sm btn-outline-primary" href="chatbot.php?q=approved_found">Approved (Found)</a>
        <a class="btn btn-sm btn-outline-primary" href="chatbot.php?q=rejected">Rejected</a>
        <a class="btn btn-sm btn-outline-primary" href="chatbot.php?q=browse_privacy">Browse privacy</a>
        <a class="btn btn-sm btn-outline-danger" href="chatbot.php?q=other">Other query</a>
      </div>

      <div class="mt-2 text-muted small">
        If your issue isn’t listed, choose <b>Other query</b> — the bot will direct you to email support.
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>