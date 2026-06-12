<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/mailer.php"; // must have send_email()

$msg = "";
$err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if ($email === "") {
        $err = "Please enter your email.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email, is_active FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();

        // Always show same message (security best practice)
        $msg = "If that email exists, a reset code has been sent.";

        if ($u) {
            if ((int)($u["is_active"] ?? 1) === 0) {
                // still show generic msg
            } else {
                // rate limit (optional)
                $now = new DateTime("now");
                $cooldownSeconds = 30;

                $check = $conn->prepare("SELECT reset_last_sent_at FROM users WHERE id=?");
                $check->bind_param("i", $u["id"]);
                $check->execute();
                $row = $check->get_result()->fetch_assoc();

                if (!empty($row["reset_last_sent_at"])) {
                    $last = new DateTime($row["reset_last_sent_at"]);
                    $diff = $now->getTimestamp() - $last->getTimestamp();
                    if ($diff < $cooldownSeconds) {
                        // still show generic msg
                        header("Location: forgot_password.php?sent=1");
                        exit;
                    }
                }

                $code = strval(random_int(100000, 999999)); // 6-digit code
                $hash = password_hash($code, PASSWORD_DEFAULT);
                $expires = (new DateTime("+10 minutes"))->format("Y-m-d H:i:s");
                $sentAt = (new DateTime())->format("Y-m-d H:i:s");

                $up = $conn->prepare("UPDATE users SET reset_code_hash=?, reset_expires_at=?, reset_last_sent_at=? WHERE id=?");
                $up->bind_param("sssi", $hash, $expires, $sentAt, $u["id"]);
                $up->execute();

                // Email user
                $subject = "VU LostLink - Password Reset Code";
                $body = "
                  <h3>VU LostLink</h3>
                  <p>Hello " . htmlspecialchars($u["full_name"]) . ",</p>
                  <p>Your password reset code is:</p>
                  <h2 style='letter-spacing:2px;'>" . htmlspecialchars($code) . "</h2>
                  <p>This code expires in 10 minutes.</p>
                ";

                send_email($u["email"], $u["full_name"], $subject, $body);
            }
        }

        header("Location: forgot_password.php?sent=1");
        exit;
    }
}

$sent = isset($_GET["sent"]);
require_once __DIR__ . "/partials/header.php";
?>
<div class="container mt-4" style="max-width:520px;">
  <h2 class="section-title">Forgot Password</h2>

  <div class="card">
    <?php if($sent): ?>
      <div class="alert alert-success">
        If that email exists, a reset code has been sent. Check your inbox.
      </div>
      <a class="btn btn-primary w-100" href="reset_password.php">Enter Reset Code</a>
      <a class="btn btn-outline-primary w-100 mt-2" href="login.php">Back to Login</a>
    <?php else: ?>
      <?php if($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Your account email</label>
          <input class="form-control" type="email" name="email" required>
        </div>
        <button class="btn btn-primary w-100">Send Reset Code</button>
        <a class="btn btn-outline-primary w-100 mt-2" href="login.php">Back to Login</a>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . "/partials/footer.php"; ?>