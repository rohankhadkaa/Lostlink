<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_admin();

$verification_id = (int)($_GET["verification_id"] ?? 0);
if ($verification_id <= 0) {
    header("Location: admin_dashboard.php");
    exit;
}

// Load verification + item + user
$stmt = $conn->prepare("
    SELECT v.*,
           li.item_name, li.description, li.item_type, li.picture,
           u.full_name, u.email
    FROM verifications v
    JOIN lost_items li ON li.id = v.item_id
    JOIN users u ON u.id = v.user_id
    WHERE v.id = ?
    LIMIT 1
");
if (!$stmt) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $verification_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Not found.");
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="page">
  <div class="shell">

    <div class="page-head">
      <div>
        <h1>Review Answers</h1>
        <p>Check item details and the user’s verification responses.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="admin_dashboard.php" class="btn btn-outline-primary">
          <i class="bi bi-arrow-left me-2"></i>Back to Admin
        </a>
      </div>
    </div>

    <!-- Item details -->
    <div class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">Item Details</h2>
      </div>
      <div class="panel-body">
        <div class="row g-3 align-items-start">
          <div class="col-md-8">
            <div class="mb-2"><b>User:</b> <?= htmlspecialchars($data["full_name"] ?? "") ?></div>
            <div class="mb-2"><b>Email:</b> <?= htmlspecialchars($data["email"] ?? "") ?></div>
            <div class="mb-2"><b>Item:</b> <?= htmlspecialchars($data["item_name"] ?? "") ?></div>
            <div class="mb-2"><b>Type:</b> <?= strtoupper(htmlspecialchars($data["item_type"] ?? "lost")) ?></div>
            <div class="mb-0"><b>Description:</b><br><?= nl2br(htmlspecialchars($data["description"] ?? "")) ?></div>
          </div>

          <div class="col-md-4">
            <?php if (!empty($data["picture"])): ?>
              <img
                src="uploads/<?= htmlspecialchars($data["picture"]) ?>"
                class="img-fluid rounded border"
                alt="Item image"
                style="max-height:240px; object-fit:cover;"
              >
            <?php else: ?>
              <div class="text-muted">No Image</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Q&A -->
    <div class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">Questions & Answers</h2>
      </div>
      <div class="panel-body">
        <div class="mb-3">
          <b>Q1:</b> <?= htmlspecialchars($data["question1"] ?? "") ?>
        </div>
        <div class="mb-3">
          <b>A1:</b><br><?= nl2br(htmlspecialchars($data["answer1"] ?? "")) ?>
        </div>

        <?php if (!empty($data["question2"])): ?>
          <hr class="hr-soft">
          <div class="mb-3">
            <b>Q2:</b> <?= htmlspecialchars($data["question2"]) ?>
          </div>
          <div class="mb-0">
            <b>A2:</b><br><?= nl2br(htmlspecialchars($data["answer2"] ?? "")) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Actions -->
    <div class="d-flex gap-2 flex-wrap">
      <a href="admin_dashboard.php" class="btn btn-outline-primary">Back</a>

      <a class="btn btn-success"
         href="admin_dashboard.php?approve=<?= (int)$data["id"] ?>"
         onclick="return confirm('Approve this claim?');">
         Approve
      </a>

      <a class="btn btn-danger"
         href="admin_dashboard.php?reject=<?= (int)$data["id"] ?>"
         onclick="return confirm('Reject this claim?');">
         Reject
      </a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>