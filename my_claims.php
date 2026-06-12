<?php
// my_claims.php — a user tracks the status of their own claims
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_login();

$uid = (int)($_SESSION["user_id"] ?? 0);

$st = $conn->prepare("
  SELECT c.id, c.status, c.created_at, li.item_name, li.item_type
  FROM item_claims c
  JOIN lost_items li ON li.id = c.item_id
  WHERE c.claimant_id = ?
  ORDER BY c.created_at DESC
");
$st->bind_param("i", $uid);
$st->execute();
$claims = $st->get_result();

$STATUS_LABELS = [
  'submitted'            => ['Submitted', 'secondary'],
  'under_review'         => ['Under Review', 'warning text-dark'],
  'verification'         => ['Verification in Progress', 'info'],
  'awaiting_response'    => ['Awaiting Claimant Response', 'primary'],
  'verified'             => ['Verified', 'success'],
  'ready_for_collection' => ['Ready for Collection', 'success'],
  'collected'            => ['Collected', 'dark'],
  'rejected'             => ['Rejected', 'danger'],
];

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4" style="max-width:1000px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0">My Claims</h2>
    <a href="browse_items.php" class="btn btn-outline-primary">Browse Items</a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-bordered bg-white mb-0">
        <thead class="table-dark">
          <tr>
            <th>Date</th>
            <th>Item</th>
            <th>Type</th>
            <th>Status</th>
            <th style="width:160px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($claims && $claims->num_rows): ?>
          <?php while ($c = $claims->fetch_assoc()):
            [$label, $cls] = $STATUS_LABELS[$c["status"]] ?? [ucfirst($c["status"]), 'secondary'];
          ?>
            <tr>
              <td><?= htmlspecialchars($c["created_at"]) ?></td>
              <td><?= htmlspecialchars($c["item_name"]) ?></td>
              <td><?= strtoupper(htmlspecialchars($c["item_type"])) ?></td>
              <td><span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($label) ?></span></td>
              <td>
                <a class="btn btn-sm btn-primary" href="claim_view.php?id=<?= (int)$c["id"] ?>">View / Messages</a>
                <?php if ($c["status"] === 'awaiting_response'): ?>
                  <a class="btn btn-sm btn-warning" href="respond_verification.php?claim_id=<?= (int)$c["id"] ?>">Respond</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" class="text-center text-muted py-4">
            You have no claims yet. Find an item in <a href="browse_items.php">Browse</a> and click Claim.
          </td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>