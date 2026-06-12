<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/mailer.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $inputPass = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT id, full_name, password, role, is_active FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();

        if ((int)$row["is_active"] !== 1) {
            $error = "Account is disabled. Contact admin at lostlink.vu@gmail.com.";
        } else {
            $stored = $row["password"];

            $isValid = (!empty($stored) && $stored[0] === '$')
                ? password_verify($inputPass, $stored)
                : (md5($inputPass) === $stored);

            if ($isValid) {

                // Upgrade old md5 passwords automatically
                if (!empty($stored) && $stored[0] !== '$') {
                    $newHash = password_hash($inputPass, PASSWORD_DEFAULT);
                    $up = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                    $up->bind_param("si", $newHash, $row["id"]);
                    $up->execute();
                }

                // Generate OTP
                $otp = strval(random_int(100000, 999999));
                $otpHash = password_hash($otp, PASSWORD_DEFAULT);
                $expires = (new DateTime())
                    ->modify("+" . OTP_TTL_MINUTES . " minutes")
                    ->format("Y-m-d H:i:s");

                $up2 = $conn->prepare("UPDATE users SET otp_code_hash=?, otp_expires_at=?, otp_last_sent_at=NOW() WHERE id=?");
                $up2->bind_param("ssi", $otpHash, $expires, $row["id"]);
                $up2->execute();

                $_SESSION["pending_user_id"] = (int)$row["id"];
                $_SESSION["pending_email"] = $email;

                $subject = "VU LostLink OTP Code";
                $body = "
                  <h3>VU LostLink</h3>
                  <p>Your OTP code is:</p>
                  <h2 style='letter-spacing:2px;'>$otp</h2>
                  <p>This code expires in ".OTP_TTL_MINUTES." minutes.</p>
                ";

                send_email($email, $row["full_name"], $subject, $body);

                header("Location: otp.php");
                exit;
            }
        }
    }

    $error = $error ?: "Invalid email or password.";
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-5" style="max-width:520px;">
  <div class="text-center mb-4">
    <h2 style="font-weight:700; color:#1f3c88;">VU LOSTLINK</h2>
    <p class="text-muted">Login requires an OTP sent to your email.</p>
  </div>

  <div class="card">
    <?php if($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required>
      </div>

      <button class="btn btn-primary w-100">Send OTP</button>

      <!-- Forgot Password Link -->
      <div class="mt-3 text-center">
        <a href="forgot_password.php" class="text-decoration-none">
          Forgot password?
        </a>
      </div>

    </form>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>