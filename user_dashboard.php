<?php
// user_dashboard.php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/notifications.php";

require_user(); // must be logged in and not admin

$user_id = (int)$_SESSION['user_id'];

$search = trim($_GET['search'] ?? '');
$type   = strtolower(trim($_GET['type'] ?? 'all')); // all|lost|found

$params = [$user_id];
$types = "i";

$sql = "
SELECT li.*,
       v.id AS verification_id,
       v.status AS v_status,
       v.question1
FROM lost_items li
LEFT JOIN verifications v ON li.id = v.item_id
WHERE li.user_id = ?
";

// Search within own uploads
if ($search !== "") {
    $sql .= " AND (li.item_name LIKE ? OR li.description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

// Filter lost/found within own uploads
if (in_array($type, ['lost','found'], true)) {
    $sql .= " AND li.item_type = ?";
    $params[] = $type;
    $types .= "s";
}

$sql .= " ORDER BY li.date_lost DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4" style="max-width:1100px;">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="m-0">Welcome <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></h2>
    <div class="d-flex gap-2 flex-wrap">
      <a href="upload_lost_item.php" class="btn btn-primary">Upload Item</a>
      <a href="browse_items.php" class="btn btn-outline-primary">Browse Items (Limited)</a>
      <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
  </div>

  <!-- Search + Filter (your own uploads) -->
  <div class="card mt-3">
    <h5 class="mb-3">My Uploaded Items</h5>

    <form method="GET" class="mb-3">
      <div class="row g-2">
        <div class="col-md-8">
          <input type="text" name="search" class="form-control"
                 placeholder="Search your uploads (name/keywords)..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
          <select name="type" class="form-select">
            <option value="all"  <?= ($type==='all'?'selected':'') ?>>All</option>
            <option value="lost" <?= ($type==='lost'?'selected':'') ?>>Lost</option>
            <option value="found"<?= ($type==='found'?'selected':'') ?>>Found</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100">Search</button>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered bg-white mb-0">
        <thead class="table-dark">
          <tr>
            <th>Item</th>
            <th>Type</th>
            <th>Status</th>
            <th style="width:160px;">Action</th>
          </tr>
        </thead>
        <tbody>

        <?php while($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['item_name'] ?? '') ?></td>
            <td><?= strtoupper($row['item_type'] ?? 'lost') ?></td>

            <td>
              <?php
                $vStatus = $row['v_status'] ?? '';
                $itemType = strtolower($row['item_type'] ?? 'lost');

                if (empty($row['verification_id'])) {
                  echo "<span class='badge bg-secondary'>Waiting Admin Questions</span>";
                } elseif ($vStatus === 'pending' && !empty($row['question1'])) {
                  echo "<span class='badge bg-warning text-dark'>Answer Questions</span>";
                } elseif ($vStatus === 'answered') {
                  echo "<span class='badge bg-info'>Waiting Admin Review</span>";
                } elseif ($vStatus === 'approved') {
                  echo "<span class='badge bg-success'>Approved</span>";

                  if ($itemType === 'found') {
                    echo "<div class='mt-2 text-success fw-semibold'>
                            Please bring the item to Level G (Uni Building) and hand it to staff.
                          </div>";
                  } else {
                    echo "<div class='mt-2 text-success fw-semibold'>
                            Please collect your item at Level G (Uni Building).
                          </div>";
                  }
                } elseif ($vStatus === 'rejected') {
                  echo "<span class='badge bg-danger'>Rejected</span>
                        <div class='mt-2 text-muted'>
                          If you believe this is incorrect, contact Admin.
                        </div>";
                } else {
                  echo "<span class='badge bg-secondary'>Unknown</span>";
                }
              ?>
            </td>

            <td>
              <?php if ($vStatus === 'pending' && !empty($row['question1'])): ?>
                <a class="btn btn-primary btn-sm"
                   href="answer_verification.php?verification_id=<?= (int)$row['verification_id'] ?>">
                   Answer
                </a>
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>

          </tr>
        <?php endwhile; ?>

        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>