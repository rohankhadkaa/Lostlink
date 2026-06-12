<?php
// admin_claims.php — admin claims dashboard (stats + search + filter)
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_admin();

$filter = strtolower(trim($_GET["status"] ?? "all"));
$q      = trim($_GET["q"] ?? "");

// ---- Remove (delete) a claim and its related records ----
if (isset($_GET["delete"])) {
    $cid = (int)$_GET["delete"];
    foreach (["claim_messages","claim_verifications","claim_audit"] as $tbl) {
        $d = $conn->prepare("DELETE FROM $tbl WHERE claim_id=?");
        $d->bind_param("i", $cid);
        $d->execute();
    }
    $d = $conn->prepare("DELETE FROM item_claims WHERE id=?");
    $d->bind_param("i", $cid);
    $d->execute();
    header("Location: admin_claims.php?msg=deleted");
    exit;
}

$STATUS_LABELS = [
  'submitted'            => ['Claimed', 'secondary'],
  'under_review'         => ['Under Review', 'warning text-dark'],
  'verification'         => ['Verification in Progress', 'info'],
  'awaiting_response'    => ['Awaiting Claimant Response', 'primary'],
  'verified'             => ['Verified', 'success'],
  'ready_for_collection' => ['Ready for Collection', 'success'],
  'collected'            => ['Collected', 'dark'],
  'rejected'             => ['Rejected', 'danger'],
];

// ---- Statistics: counts per status ----
$stats = array_fill_keys(array_keys($STATUS_LABELS), 0);
$total = 0;
if ($r = $conn->query("
        SELECT c.status AS status, COUNT(*) AS cnt
        FROM item_claims c
        JOIN lost_items li ON li.id = c.item_id
        JOIN users u       ON u.id  = c.claimant_id
        GROUP BY c.status")) {
    while ($row = $r->fetch_assoc()) {
        if (isset($stats[$row["status"]])) { $stats[$row["status"]] = (int)$row["cnt"]; }
        $total += (int)$row["cnt"];
    }
}

// ---- List query with optional search + status filter ----
$sql = "
  SELECT c.id, c.claimant_name, c.contact_info, c.status, c.created_at,
         li.item_name,
         u.full_name AS account_name, u.email AS account_email
  FROM item_claims c
  JOIN lost_items li ON li.id = c.item_id
  JOIN users u       ON u.id  = c.claimant_id
";
$conds = []; $params = []; $types = "";

if (isset($STATUS_LABELS[$filter])) {
    $conds[] = "c.status = ?";
    $params[] = $filter; $types .= "s";
}
if ($q !== "") {
    // match item name, claimant name, account name, or numeric claim ID
    $like = "%$q%";
    $idMatch = ctype_digit($q) ? (int)$q : 0;
    $conds[] = "(li.item_name LIKE ? OR c.claimant_name LIKE ? OR u.full_name LIKE ? OR c.id = ?)";
    array_push($params, $like, $like, $like, $idMatch);
    $types .= "sssi";
}
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY c.created_at DESC";

$st = $conn->prepare($sql);
if ($types !== "") { $st->bind_param($types, ...$params); }
$st->execute();
$claims = $st->get_result();

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="m-0">Claims Dashboard</h2>
    <a href="admin_dashboard.php" class="btn btn-outline-primary">Back to Admin</a>
  </div>

  <?php if (($_GET["msg"] ?? "") === "deleted"): ?>
    <div class="alert alert-success">Claim removed.</div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="row row-cols-2 row-cols-md-3 g-3 mb-3">
    <div class="col">
      <div class="card text-center h-100"><div class="h4 m-0"><span class="badge bg-primary"><?= $total ?></span></div><div class="small text-muted">Total</div></div>
    </div>
    <?php foreach ($STATUS_LABELS as $key => [$lbl,$cls]): ?>
      <div class="col">
        <a href="?status=<?= $key ?>" class="text-decoration-none">
          <div class="card text-center h-100">
            <div class="h4 m-0"><span class="badge bg-<?= $cls ?>"><?= $stats[$key] ?></span></div>
            <div class="small text-muted"><?= htmlspecialchars($lbl) ?></div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Search + filter -->
  <form method="GET" class="mb-3">
    <div class="row g-2">
      <div class="col-md-6">
        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>"
               placeholder="Search by item name, claimant name, or claim ID...">
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value="all" <?= $filter==='all'?'selected':'' ?>>All statuses</option>
          <?php foreach ($STATUS_LABELS as $key => [$lbl,$x]): ?>
            <option value="<?= $key ?>" <?= $filter===$key?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-fill">Search</button>
        <a href="admin_claims.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </div>
  </form>

  <table class="table table-bordered bg-white">
    <thead class="table-dark">
      <tr>
        <th>Claim #</th>
        <th>Date</th>
        <th>Item</th>
        <th>Claimant</th>
        <th>Contact</th>
        <th>Status</th>
        <th style="width:170px;">Action</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($claims && $claims->num_rows): ?>
      <?php while ($c = $claims->fetch_assoc()):
        [$label, $cls] = $STATUS_LABELS[$c["status"]] ?? [ucfirst($c["status"]), 'secondary'];
      ?>
        <tr>
          <td>#<?= (int)$c["id"] ?></td>
          <td><?= htmlspecialchars($c["created_at"]) ?></td>
          <td><?= htmlspecialchars($c["item_name"]) ?></td>
          <td><?= htmlspecialchars($c["claimant_name"] ?: $c["account_name"]) ?><br>
              <small class="text-muted"><?= htmlspecialchars($c["account_email"]) ?></small></td>
          <td><?= htmlspecialchars($c["contact_info"] ?? '-') ?></td>
          <td><span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($label) ?></span></td>
          <td class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-primary" href="claim_view.php?id=<?= (int)$c["id"] ?>">Manage</a>
            <a class="btn btn-sm btn-outline-danger" href="admin_claims.php?delete=<?= (int)$c["id"] ?>"
               onclick="return confirm('Permanently remove this claim and its messages/history? This cannot be undone.');">Remove</a>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="7" class="text-center text-muted py-4">No claims found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>