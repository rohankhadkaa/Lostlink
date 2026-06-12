<?php
require_once __DIR__ . "/config.php";
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION["pending_user_id"])) {
  header("Location: login.php");
  exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $otp = trim($_POST["otp"] ?? "");
  $uid = (int)$_SESSION["pending_user_id"];

  $stmt = $conn->prepare("SELECT id, full_name, role, otp_code_hash, otp_expires_at FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if (!$row || empty($row["otp_code_hash"]) || empty($row["otp_expires_at"])) {
    $error = "OTP not available. Please login again.";
  } else {
    $now = new DateTime();
    $exp = new DateTime($row["otp_expires_at"]);

    if ($now > $exp) {
      $error = "OTP expired. Please login again.";
    } elseif (!password_verify($otp, $row["otp_code_hash"])) {
      $error = "Invalid OTP code.";
    } else {
      // Clear OTP fields
      $clr = $conn->prepare("UPDATE users SET otp_code_hash=NULL, otp_expires_at=NULL WHERE id=?");
      $clr->bind_param("i", $uid);
      $clr->execute();

      // Complete login
      $_SESSION["user_id"] = (int)$row["id"];
      $_SESSION["name"] = $row["full_name"];
      $_SESSION["role"] = $row["role"];

      unset($_SESSION["pending_user_id"], $_SESSION["pending_email"]);

      header("Location: dashboard.php");
      exit;
    }
  }
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-5" style="max-width:520px;">
  <h2 class="section-title">Enter OTP</h2>
  <div class="card">
    <?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">OTP Code</label>
        <input class="form-control" name="otp" maxlength="6" required>
        <div class="text-muted mt-2"> A verification code has been sent to your registered email address.
  Please enter it to continue.</div>
      </div>
      <button class="btn btn-primary w-100">Verify</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>