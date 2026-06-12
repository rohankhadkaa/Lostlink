<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/mailer.php";
require_once __DIR__ . "/notifications.php";

require_admin();

$itemId = (int)($_GET["item_id"] ?? 0);
if ($itemId <= 0) { header("Location: admin_dashboard.php"); exit; }

// Find item + owner
$stmt = $conn->prepare("
  SELECT li.id, li.item_name, li.item_type, li.user_id, u.email, u.full_name
  FROM lost_items li
  JOIN users u ON u.id = li.user_id
  WHERE li.id=? LIMIT 1
");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) { header("Location: admin_dashboard.php"); exit; }

// Check if verification already exists for this item
$chk = $conn->prepare("SELECT id FROM verifications WHERE item_id=? LIMIT 1");
$chk->bind_param("i", $itemId);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();

$err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $q1 = trim($_POST["q1"] ?? "");
  $q2 = trim($_POST["q2"] ?? "");

  if ($q1 === "") {
    $err = "Question 1 is required.";
  } else {
    if ($existing) {
      // UPDATE existing verification (follow-up / resend)
      $vid = (int)$existing["id"];
      $up = $conn->prepare("
        UPDATE verifications
        SET user_id=?, question1=?, question2=?, answer1=NULL, answer2=NULL, status='pending'
        WHERE id=?
      ");
      $up->bind_param("issi", $item["user_id"], $q1, $q2, $vid);
      $up->execute();
    } else {
      // INSERT new verification
      $ins = $conn->prepare("
        INSERT INTO verifications (item_id, user_id, question1, question2, status)
        VALUES (?, ?, ?, ?, 'pending')
      ");
      $ins->bind_param("iiss", $itemId, $item["user_id"], $q1, $q2);
      $ins->execute();
    }

    // Notify + email user
    $title = "Verification Questions Received";
    $msg = "Admin sent verification questions for: " . $item["item_name"] . ". Please answer them in your dashboard.";
    add_notification((int)$item["user_id"], $title, $msg);

    $subject = "VU LostLink - Verification Questions";
    $body = "
      <h3>VU LostLink</h3>
      <p>Hello " . htmlspecialchars($item["full_name"]) . ",</p>
      <p>Admin has sent verification questions for <b>" . htmlspecialchars($item["item_name"]) . "</b>.</p>
      <p>Please login to answer them in your dashboard.</p>
      <p><small>If you did not request this, ignore this email.</small></p>
    ";
    send_email($item["email"], $item["full_name"], $subject, $body);

    header("Location: admin_dashboard.php");
    exit;
  }
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="container" style="max-width:800px;">
  <h2 class="section-title">Send Verification Questions</h2>

  <div class="card">
    <p><b>Item:</b> <?= htmlspecialchars($item["item_name"]) ?> (<?= strtoupper($item["item_type"]) ?>)</p>
    <p><b>User:</b> <?= htmlspecialchars($item["full_name"]) ?> (<?= htmlspecialchars($item["email"]) ?>)</p>

    <?php if($existing): ?>
      <div class="alert alert-warning">
        This item already has a verification record. Submitting will <b>replace</b> questions and reset answers.
      </div>
    <?php endif; ?>

    <?php if($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Question 1 (required)</label>
        <input class="form-control" name="q1" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Question 2 (optional)</label>
        <input class="form-control" name="q2">
      </div>

      <button class="btn btn-primary"><?= $existing ? "Resend Questions" : "Send Questions" ?></button>
      <a class="btn btn-outline-primary" href="admin_dashboard.php">Back</a>
    </form>
  </div>

  <div class="text-muted mt-3">
    <small>User gets a notification regarding the questions</b>.</small>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>