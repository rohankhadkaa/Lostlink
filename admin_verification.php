<?php
// admin_verification.php — dedicated Admin Verification Portal (admin only)
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_admin(); // role-based access control: admins only

// Requested filter token (from the buttons), default 'all'
$f = trim($_GET["f"] ?? "all");

// Display badge styling per stored status
$STATUS_BADGES = [
  'submitted'            => ['Submitted', 'secondary'],
  'under_review'         => ['Under Review', 'warning text-dark'],
  'verification'         => ['Verification in Progress', 'info'],
  'ready_for_collection' => ['Ready for Collection', 'success'],
  'collected'            => ['Collected', 'dark'],
  'rejected'             => ['Rejected', 'danger'],
];

// Filter buttons (label) -> which stored status(es) they match
$FILTERS = [
  'all'                  => 'All',
  'submitted'            => 'Submitted',
  'awaiting_response'    => 'Awaiting Response',
  'under_review'         => 'Under Review',
  'verified'             => 'Verified',
  'rejected'             => 'Rejected',
  'ready_for_collection' => 'Ready for Collection',
  'collected'            => 'Collected',
];
$FILTER_TO_STATUS = [
  'submitted'            => ['submitted'],
  'awaiting_response'    => ['verification'],          // waiting on the claimant's response
  'under_review'         => ['under_review'],
  'verified'             => ['ready_for_collection'],  // a verified claim is Ready for Collection in this workflow
  'rejected'             => ['rejected'],
  'ready_for_collection' => ['ready_for_collection'],
  'collected'            => ['collected'],
];

// Build the list query with the optional status filter
$sql = "
  SELECT c.id, c.claimant_name, c.status, c.created_at,
         li.item_name, li.picture,
         u.full_name AS account_name
  FROM item_claims c
  JOIN lost_items li ON li.id = c.item_id
  JOIN users u       ON u.id  = c.claimant_id
";
$params = []; $types = "";
if (isset($FILTER_TO_STATUS[$f])) {
    $statuses = $FILTER_TO_STATUS[$f];
    $placeholders = implode(",", array_fill(0, count($statuses), "?"));
    $sql .= " WHERE c.status IN ($placeholders) ";
    foreach ($statuses as $s) { $params[] = $s; $types .= "s"; }
}
$sql .= " ORDER BY c.created_at DESC";

$st = $conn->prepare($sql);
if ($types !== "") { $st->bind_param($types, ...$params); }
$st->execute();
$claims = $st->get_result();

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="m-0">Admin Verification Portal</h2>
    <a href="admin_dashboard.php" class="btn btn-outline-primary">Back to Admin</a>
  </div>

  <!-- Filters -->
  <div class="mb-4 d-flex flex-wrap gap-2">
    <?php foreach ($FILTERS as $key => $label): ?>
      <a href="?f=<?= urlencode($key) ?>"
         class="btn btn-sm <?= ($f === $key) ? 'btn-primary' : 'btn-outline-primary' ?>">
        <?= htmlspecialchars($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Claims grid -->
  <div class="row g-3">
    <?php if ($claims && $claims->num_rows): ?>
      <?php while ($c = $claims->fetch_assoc()):
        [$label, $cls] = $STATUS_BADGES[$c["status"]] ?? [ucfirst($c["status"]), 'secondary'];
      ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card h-100">
            <?php if (!empty($c["picture"])): ?>
              <img src="uploads/<?= htmlspecialchars($c["picture"]) ?>" alt="Item image"
                   style="height:180px;object-fit:cover;" class="card-img-top">
            <?php else: ?>
              <div class="d-flex align-items-center justify-content-center bg-light text-muted"
                   style="height:180px;">No image</div>
            <?php endif; ?>

            <div class="card-body d-flex flex-column">
              <div class="mb-2">
                <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                <span class="text-muted small float-end">Claim #<?= (int)$c["id"] ?></span>
              </div>
              <h5 class="mb-1"><?= htmlspecialchars($c["item_name"]) ?></h5>
              <div class="small text-muted mb-1">
                Claimant: <?= htmlspecialchars($c["claimant_name"] ?: $c["account_name"]) ?>
              </div>
              <div class="small text-muted mb-3">
                Claimed: <?= htmlspecialchars($c["created_at"]) ?>
              </div>
              <a href="claim_view.php?id=<?= (int)$c["id"] ?>"
                 class="btn btn-primary mt-auto">Open Verification</a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-info mb-0">No claims found for this filter.</div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>