<?php
// answer_verification.php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/mailer.php";
require_once __DIR__ . "/notifications.php";

require_user(); // must be logged in and not admin

$user_id = (int)$_SESSION['user_id'];
$verification_id = (int)($_GET['verification_id'] ?? 0);

if ($verification_id <= 0) {
    header("Location: user_dashboard.php");
    exit;
}

// Load verification + item + owner check
$stmt = $conn->prepare("
    SELECT v.id, v.status, v.question1, v.question2, v.item_id,
           li.user_id AS owner_id, li.item_name,
           u.full_name, u.email
    FROM verifications v
    JOIN lost_items li ON li.id = v.item_id
    JOIN users u ON u.id = li.user_id
    WHERE v.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $verification_id);
$stmt->execute();
$v = $stmt->get_result()->fetch_assoc();

if (!$v) {
    die("Not found.");
}
if ((int)$v['owner_id'] !== $user_id) {
    die("Forbidden.");
}

// Only allow answering when verification is pending
if (($v["status"] ?? "") !== "pending") {
    die("This verification is not waiting for answers.");
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a1 = trim($_POST['answer1'] ?? '');
    $a2 = trim($_POST['answer2'] ?? '');

    if ($a1 === '') {
        $error = "Answer 1 is required.";
    } else {
        // Update answers + mark answered
        $up = $conn->prepare("UPDATE verifications SET answer1=?, answer2=?, status='answered' WHERE id=?");
        $up->bind_param("ssi", $a1, $a2, $verification_id);
        $up->execute();

        // Notify user (confirmation)
        add_notification($user_id, "Answers Submitted", "Your verification answers for '{$v["item_name"]}' were submitted successfully.");

        // Notify admin (simple approach: notify ALL admins)
        $admins = $conn->query("SELECT id, full_name, email FROM users WHERE role='admin' AND is_active=1");
        while ($ad = $admins->fetch_assoc()) {
            add_notification((int)$ad["id"], "Verification Answered",
                "User answered verification for '{$v["item_name"]}'. Please review and approve/reject.");

            // Email admin too (optional but makes it feel real)
            $subject = "VU LostLink - User Answered Verification";
            $body = "
              <h3>VU LostLink</h3>
              <p>Hello " . htmlspecialchars($ad["full_name"]) . ",</p>
              <p>A user has answered verification questions for:</p>
              <p><b>Item:</b> " . htmlspecialchars($v["item_name"]) . "</p>
              <p>Please login to Admin Dashboard to review and approve/reject.</p>
            ";
            send_email($ad["email"], $ad["full_name"], $subject, $body);
        }

        header("Location: user_dashboard.php");
        exit;
    }
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="page">
  <div class="shell page-narrow">

    <div class="page-head">
      <div>
        <h1>Answer Verification Questions</h1>
        <p>Please answer the questions below to confirm ownership of <strong><?= htmlspecialchars($v['item_name'] ?? '') ?></strong>.</p>
      </div>
    </div>

    <?php if($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Questions -->
    <div class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">Questions</h2>
        <span class="status-pill pending">Pending</span>
      </div>

      <div class="panel-body">
        <div class="qa-block">
          <div class="qa-q">
            <span class="qa-label">Q1</span>
            <div class="qa-text"><?= htmlspecialchars($v['question1'] ?? '') ?></div>
          </div>

          <?php if(!empty($v['question2'])): ?>
            <div class="qa-q">
              <span class="qa-label">Q2</span>
              <div class="qa-text"><?= htmlspecialchars($v['question2']) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Answer Form -->
    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">Your Answers</h2>
        <span class="chip">Be specific</span>
      </div>

      <div class="panel-body">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Answer 1</label>
            <textarea class="form-control" name="answer1" rows="3" required></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Answer 2 (optional)</label>
            <textarea class="form-control" name="answer2" rows="3"></textarea>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary">
              <i class="bi bi-send me-2"></i>Submit Answers
            </button>
            <a href="user_dashboard.php" class="btn btn-outline-primary">
              <i class="bi bi-arrow-left me-2"></i>Back
            </a>
          </div>
        </form>

        <div class="text-muted mt-3">
          <small>You will receive an email once an admin responds.</small>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>