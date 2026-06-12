<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_admin();

$matchId = (int)($_GET['id'] ?? 0);
if ($matchId <= 0) {
    header("Location: admin_dashboard.php");
    exit;
}

$st = $conn->prepare("
  SELECT m.*, 
         l.item_name AS lost_name, l.description AS lost_desc, l.picture AS lost_pic, l.date_lost AS lost_date,
         f.item_name AS found_name, f.description AS found_desc, f.picture AS found_pic, f.date_lost AS found_date
  FROM ai_matches m
  JOIN lost_items l ON l.id = m.lost_item_id
  JOIN lost_items f ON f.id = m.found_item_id
  WHERE m.id = ?
  LIMIT 1
");
$st->bind_param("i", $matchId);
$st->execute();
$row = $st->get_result()->fetch_assoc();

if (!$row) {
    header("Location: admin_dashboard.php");
    exit;
}

$reasons = [];
if (!empty($row['reasons'])) {
    $tmp = json_decode($row['reasons'], true);
    if (is_array($tmp)) $reasons = $tmp;
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4" style="max-width:1100px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="m-0">AI Match Details</h2>
    <a class="btn btn-outline-primary" href="admin_dashboard.php">Back</a>
  </div>

  <div class="card mb-3 p-3">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <div>
        <div><b>Match Score:</b> <?= htmlspecialchars($row['score']) ?>%</div>
        <div><b>Status:</b> <?= htmlspecialchars($row['status']) ?></div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-danger" href="admin_match_action.php?action=dismiss&id=<?= (int)$row['id'] ?>">Dismiss</a>
        <a class="btn btn-outline-success" href="admin_match_action.php?action=confirm&id=<?= (int)$row['id'] ?>">Confirm</a>
      </div>
    </div>

    <?php if ($reasons): ?>
      <hr>
      <div class="small text-muted"><b>Why it matched:</b></div>
      <ul class="mb-0">
        <?php foreach ($reasons as $r): ?>
          <li><?= htmlspecialchars((string)$r) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card p-3 h-100">
        <h4 class="mb-2">LOST Item</h4>
        <div><b>Name:</b> <?= htmlspecialchars($row['lost_name']) ?></div>
        <div><b>Date:</b> <?= htmlspecialchars(substr((string)$row['lost_date'], 0, 10)) ?></div>
        <div class="mt-2"><b>Description:</b><br><?= nl2br(htmlspecialchars($row['lost_desc'] ?? '')) ?></div>

        <div class="mt-3">
          <b>Picture:</b><br>
          <?php if (!empty($row['lost_pic'])): ?>
            <img src="uploads/<?= htmlspecialchars($row['lost_pic']) ?>" style="max-width:100%; border-radius:10px;">
          <?php else: ?>
            <div class="text-muted">No image</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card p-3 h-100">
        <h4 class="mb-2">FOUND Item</h4>
        <div><b>Name:</b> <?= htmlspecialchars($row['found_name']) ?></div>
        <div><b>Date:</b> <?= htmlspecialchars(substr((string)$row['found_date'], 0, 10)) ?></div>
        <div class="mt-2"><b>Description:</b><br><?= nl2br(htmlspecialchars($row['found_desc'] ?? '')) ?></div>

        <div class="mt-3">
          <b>Picture:</b><br>
          <?php if (!empty($row['found_pic'])): ?>
            <img src="uploads/<?= htmlspecialchars($row['found_pic']) ?>" style="max-width:100%; border-radius:10px;">
          <?php else: ?>
            <div class="text-muted">No image</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>