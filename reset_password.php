<?php
require_once __DIR__ . "/config.php";
session_start();

$err = "";
$ok = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $code  = trim($_POST["code"] ?? "");
    $pass1 = $_POST["new_password"] ?? "";
    $pass2 = $_POST["confirm_password"] ?? "";

    if ($email === "" || $code === "" || $pass1 === "" || $pass2 === "") {
        $err = "Please fill all fields.";
    } elseif ($pass1 !== $pass2) {
        $err = "Passwords do not match.";
    } elseif (strlen($pass1) < 6) {
        $err = "Password must be at least 6 characters.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, reset_code_hash, reset_expires_at, is_active FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();

        if (!$u) {
            $err = "Invalid email or code.";
        } elseif ((int)($u["is_active"] ?? 1) === 0) {
            $err = "This account is disabled. Contact lostlink.vu@gmail.com";
        } elseif (empty($u["reset_code_hash"]) || empty($u["reset_expires_at"])) {
            $err = "No active reset request. Please request a new code.";
        } else {
            $expires = strtotime($u["reset_expires_at"]);
            if ($expires < time()) {
                $err = "Reset code expired. Please request a new one.";
            } elseif (!password_verify($code, $u["reset_code_hash"])) {
                $err = "Invalid email or code.";
            } else {
                $newHash = password_hash($pass1, PASSWORD_DEFAULT);

                $up = $conn->prepare("UPDATE users SET password=?, reset_code_hash=NULL, reset_expires_at=NULL WHERE id=?");
                $up->bind_param("si", $newHash, $u["id"]);
                $up->execute();

                $ok = "Password updated. You can now login.";
            }
        }
    }
}

require_once __DIR__ . "/partials/header.php";
?>
<div class="container mt-4" style="max-width:520px;">
  <h2 class="section-title">Reset Password</h2>

  <div class="card">
    <?php if($ok): ?>
      <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
      <a class="btn btn-primary w-100" href="login.php">Go to Login</a>
    <?php else: ?>
      <?php if($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Reset Code</label>
          <input class="form-control" type="text" name="code" maxlength="6" required>
        </div>

        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input class="form-control" type="password" name="new_password" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Confirm Password</label>
          <input class="form-control" type="password" name="confirm_password" required>
        </div>

        <button class="btn btn-primary w-100">Reset Password</button>
        <a class="btn btn-outline-primary w-100 mt-2" href="forgot_password.php">Request new code</a>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . "/partials/footer.php"; ?>