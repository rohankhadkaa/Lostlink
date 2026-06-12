<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/mailer.php";
require_once __DIR__ . "/notifications.php";

require_admin();

$verificationId = (int)($_GET["verification_id"] ?? 0);
if ($verificationId <= 0) { header("Location: admin_dashboard.php"); exit; }

$st = $conn->prepare("
  SELECT v.*, li.item_name, li.item_type, u.id AS uid, u.full_name, u.email
  FROM verifications v
  JOIN lost_items li ON li.id = v.item_id
  JOIN users u ON u.id = v.user_id
  WHERE v.id=? LIMIT 1
");
$st->bind_param("i", $verificationId);
$st->execute();
$row = $st->get_result()->fetch_assoc();

if(!$row) { header("Location: admin_dashboard.php"); exit; }

$error = "";

if($_SERVER["REQUEST_METHOD"] === "POST") {
    $q1 = trim($_POST["question1"] ?? "");
    $q2 = trim($_POST["question2"] ?? "");

    if ($q1 === "") {
        $error = "Question 1 is required.";
    } else {
        // overwrite questions, clear answers, set pending
        $up = $conn->prepare("
          UPDATE verifications
          SET question1=?, question2=?, answer1=NULL, answer2=NULL, status='pending'
          WHERE id=?
        ");
        $up->bind_param("ssi", $q1, $q2, $verificationId);
        $up->execute();

        // notify + email
        add_notification((int)$row["uid"], "More Verification Needed",
            "Admin requested more information for '{$row["item_name"]}'. Please answer the new questions.");

        $subject = "VU LostLink - More Verification Questions";
        $body = "
          <h3>VU LostLink</h3>
          <p>Hello ".htmlspecialchars($row["full_name"]).",</p>
          <p>Admin requested more verification for <b>".htmlspecialchars($row["item_name"])."</b>.</p>
          <p>Please login and answer the new questions.</p>
        ";
        send_email($row["email"], $row["full_name"], $subject, $body);

        header("Location: admin_dashboard.php");
        exit;
    }
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="page">
  <div class="shell page-narrow">

    <div class="page-head">
      <div>
        <h1>Request More Verification</h1>
        <p>Send follow-up questions to confirm ownership.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="admin_dashboard.php">
          <i class="bi bi-arrow-left me-2"></i>Back to Admin
        </a>
      </div>
    </div>

    <div class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">Item &amp; User</h2>
        <span class="chip">Follow-up required</span>
      </div>

      <div class="panel-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="notice">
              <div><strong>Item:</strong> <?= htmlspecialchars($row["item_name"]) ?> (<?= strtoupper($row["item_type"]) ?>)</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="notice">
              <div><strong>User:</strong> <?= htmlspecialchars($row["full_name"]) ?></div>
              <div class="small" style="opacity:.9;"><?= htmlspecialchars($row["email"]) ?></div>
            </div>
          </div>
        </div>

        <?php if($error): ?>
          <div class="alert alert-danger mt-3 mb-0"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">New Questions</h2>
        <span class="status-pill pending">Pending</span>
      </div>

      <div class="panel-body">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">New Question 1 (required)</label>
            <input class="form-control" name="question1" required>
            <div class="form-text">Ask something only the real owner would know.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">New Question 2</label>
            <input class="form-control" name="question2">
            <div class="form-text">Optional second question for extra confidence.</div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary">
              <i class="bi bi-send me-2"></i>Send Follow-up Questions
            </button>
            <a class="btn btn-outline-primary" href="admin_dashboard.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
  // Frontend-only polish: feel like a separate page
  window.scrollTo({ top: 0, behavior: "instant" });
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>