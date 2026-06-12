<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/partials/header.php";

$err = "";
$ok = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST["full_name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $pass = $_POST["password"] ?? "";

  if ($name === "" || $email === "" || $pass === "") {
    $err = "All fields are required.";
  } else {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $role = "user";

    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
    try {
      $stmt->bind_param("ssss", $name, $email, $hash, $role);
      $stmt->execute();
      $ok = "Account created. You can now login.";
    } catch (Throwable $e) {
      $err = "Email already exists.";
    }
  }
}
?>

<div class="container" style="max-width:600px;">
  <h2 class="section-title">Register</h2>

  <?php if($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

  <div class="card">
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input class="form-control" name="full_name" required>
      </div>
      <div class="mb-3">
        <label class="form-label">VU Email</label>
        <input class="form-control" type="email" name="email" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required>
      </div>
      <button class="btn btn-primary w-100">Create Account</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>