<?php
// admin_dashboard.php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/mailer.php";
require_once __DIR__ . "/notifications.php";

require_admin(); // must be logged in + role admin

$search = trim($_GET['search'] ?? '');
$type   = strtolower(trim($_GET['type'] ?? 'all')); // all|lost|found

// Fetch owner & item details for a verification id
function get_verification_info(mysqli $conn, int $verificationId): ?array {
    $sql = "
      SELECT v.id AS verification_id, v.status, v.item_id, v.user_id,
             li.item_name, li.item_type,
             u.full_name, u.email
      FROM verifications v
      JOIN lost_items li ON li.id = v.item_id
      JOIN users u ON u.id = v.user_id
      WHERE v.id = ?
      LIMIT 1
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $verificationId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ?: null;
}

// -------------------- APPROVE --------------------
if (isset($_GET['approve'])) {
    $vid = (int)$_GET['approve'];

    $stmt = $conn->prepare("UPDATE verifications SET status='approved' WHERE id=?");
    $stmt->bind_param("i", $vid);
    $stmt->execute();

    $info = get_verification_info($conn, $vid);
    if ($info) {
        $isFound = (strtolower($info["item_type"] ?? "") === "found");

        $title = "Claim Approved";
        $message = $isFound
            ? "Your FOUND item report for '{$info['item_name']}' has been approved. Please bring the item to Level G (Uni Building) and hand it to staff."
            : "Your LOST item claim for '{$info['item_name']}' has been approved. Please collect your item at Level G (Uni Building).";

        add_notification((int)$info["user_id"], $title, $message);

        $subject = "VU LostLink - Approved";
        $body = "
          <h3>VU LostLink</h3>
          <p>Hello " . htmlspecialchars($info["full_name"]) . ",</p>
          <p><b>Status:</b> APPROVED</p>
          <p><b>Item:</b> " . htmlspecialchars($info["item_name"]) . "</p>
          <p>" . htmlspecialchars($message) . "</p>
          <p>Thank you.</p>
        ";
        send_email($info["email"], $info["full_name"], $subject, $body);
    }

    header("Location: admin_dashboard.php");
    exit;
}

// -------------------- REJECT --------------------
if (isset($_GET['reject'])) {
    $vid = (int)$_GET['reject'];

    $stmt = $conn->prepare("UPDATE verifications SET status='rejected' WHERE id=?");
    $stmt->bind_param("i", $vid);
    $stmt->execute();

    $info = get_verification_info($conn, $vid);
    if ($info) {
        $title = "Claim Rejected";
        $message = "Your claim/report for '{$info['item_name']}' has been rejected. If you believe this is incorrect, please contact admin.";

        add_notification((int)$info["user_id"], $title, $message);

        $subject = "VU LostLink - Rejected";
        $body = "
          <h3>VU LostLink</h3>
          <p>Hello " . htmlspecialchars($info["full_name"]) . ",</p>
          <p><b>Status:</b> REJECTED</p>
          <p><b>Item:</b> " . htmlspecialchars($info["item_name"]) . "</p>
          <p>" . htmlspecialchars($message) . "</p>
          <p>Thank you.</p>
        ";
        send_email($info["email"], $info["full_name"], $subject, $body);
    }

    header("Location: admin_dashboard.php");
    exit;
}

// -------------------- ASK MORE (FOLLOW-UP QUESTIONS) --------------------
if (isset($_GET["ask_more"])) {
    $vid = (int)$_GET["ask_more"];

    // show the follow-up form below in the same page (no redirect)
    $askMoreInfo = get_verification_info($conn, $vid);
    if (!$askMoreInfo) {
        header("Location: admin_dashboard.php");
        exit;
    }
}

// handle follow-up submit
if (isset($_POST["followup_submit"])) {
    $vid = (int)($_POST["verification_id"] ?? 0);
    $q1  = trim($_POST["question1"] ?? "");
    $q2  = trim($_POST["question2"] ?? "");

    if ($vid > 0 && $q1 !== "") {
        $info = get_verification_info($conn, $vid);

        // overwrite questions, clear answers, set pending again
        $up = $conn->prepare("
          UPDATE verifications
          SET question1=?, question2=?, answer1=NULL, answer2=NULL, status='pending'
          WHERE id=?
        ");
        $up->bind_param("ssi", $q1, $q2, $vid);
        $up->execute();

        if ($info) {
            add_notification((int)$info["user_id"], "More Verification Needed",
                "Admin requested more information for '{$info["item_name"]}'. Please answer the new questions.");

            $subject = "VU LostLink - More Verification Questions";
            $body = "
              <h3>VU LostLink</h3>
              <p>Hello " . htmlspecialchars($info["full_name"]) . ",</p>
              <p>Admin requested more verification for <b>" . htmlspecialchars($info["item_name"]) . "</b>.</p>
              <p>Please login and answer the new questions in your dashboard.</p>
            ";
            send_email($info["email"], $info["full_name"], $subject, $body);
        }
    }

    header("Location: admin_dashboard.php");
    exit;
}

// -------------------- DELETE ITEM (ADMIN ONLY) --------------------
if (isset($_GET['delete'])) {
    $itemId = (int)$_GET['delete'];

    // remove claims tied to this item (and their child records) so nothing is orphaned
    if ($conn->query("SHOW TABLES LIKE 'item_claims'")->num_rows > 0) {
        $cl = $conn->prepare("SELECT id FROM item_claims WHERE item_id=?");
        $cl->bind_param("i", $itemId); $cl->execute();
        $clr = $cl->get_result(); $claimIds = [];
        while ($row = $clr->fetch_assoc()) { $claimIds[] = (int)$row["id"]; }
        foreach ($claimIds as $cid) {
            foreach (["claim_messages","claim_verifications","claim_audit"] as $t) {
                $d = $conn->prepare("DELETE FROM $t WHERE claim_id=?");
                $d->bind_param("i", $cid); $d->execute();
            }
        }
        $d = $conn->prepare("DELETE FROM item_claims WHERE item_id=?");
        $d->bind_param("i", $itemId); $d->execute();
    }

    // delete verification rows first (FK safe)
    $stmt = $conn->prepare("DELETE FROM verifications WHERE item_id=?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();

    // delete item
    $stmt = $conn->prepare("DELETE FROM lost_items WHERE id=?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();

    header("Location: admin_dashboard.php");
    exit;
}

// -------------------- LIST QUERY --------------------
$whereParts = [];
$params = [];
$types = "";

if ($search !== "") {
    $whereParts[] = "(li.item_name LIKE ? OR li.description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if (in_array($type, ['lost','found'], true)) {
    $whereParts[] = "li.item_type = ?";
    $params[] = $type;
    $types .= "s";
}

$whereSql = $whereParts ? ("WHERE " . implode(" AND ", $whereParts)) : "";

$sql = "
SELECT li.*, u.full_name,
       v.id AS verification_id,
       v.status AS v_status
FROM lost_items li
JOIN users u ON li.user_id = u.id
LEFT JOIN verifications v ON li.id = v.item_id
$whereSql
ORDER BY li.date_lost DESC
";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard - VU LostLink</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php require_once __DIR__ . "/partials/header.php"; ?>

<div class="container mt-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0">Admin Dashboard</h2>
    <div>
      <a href="admin_claims.php" class="btn btn-outline-primary">Review Claims</a>
      <a href="admin_users.php" class="btn btn-outline-primary">Manage Users</a>
      <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
  </div>

  <!-- FOLLOW-UP QUESTIONS FORM (appears when admin clicks Ask More) -->
  <?php if (!empty($askMoreInfo)): ?>
    <div class="card mb-3">
      <h5 class="mb-2">Ask More Questions</h5>
      <p class="text-muted mb-3">
        Item: <b><?= htmlspecialchars($askMoreInfo["item_name"]) ?></b>
      </p>
      <form method="POST">
        <input type="hidden" name="verification_id" value="<?= (int)$askMoreInfo["verification_id"] ?>">
        <div class="mb-2">
          <label class="form-label">New Question 1 (required)</label>
          <input class="form-control" name="question1" required>
        </div>
        <div class="mb-2">
          <label class="form-label">New Question 2</label>
          <input class="form-control" name="question2">
        </div>
        <button class="btn btn-warning" name="followup_submit" value="1">Send Follow-up</button>
        <a class="btn btn-outline-primary" href="admin_dashboard.php">Cancel</a>
      </form>
    </div>
  <?php endif; ?>

  <!-- Search + Filter -->
  <form method="GET" class="mb-3">
    <div class="row g-2">
      <div class="col-md-8">
        <input type="text" name="search" class="form-control"
               placeholder="Search item name or description..."
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

  <table class="table table-bordered bg-white">
    <thead class="table-dark">
      <tr>
        <th style="width:15%;">Item</th>
        <th style="width:25%;">Description</th>
        <th style="width:12%;">User</th>
        <th style="width:8%;">Type</th>
        <th style="width:12%;">Picture</th>
        <th style="width:12%;">Status</th>
        <th style="width:16%;">Actions</th>
      </tr>
    </thead>
    <tbody>

    <?php while($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['item_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['full_name'] ?? '') ?></td>
        <td><?= strtoupper($row['item_type'] ?? 'lost') ?></td>

        <td>
          <?php if(!empty($row['picture'])): ?>
            <img src="uploads/<?= htmlspecialchars($row['picture']) ?>" width="80" alt="Item">
          <?php else: ?>
            No Image
          <?php endif; ?>
        </td>

        <td>
          <?php
            if (!empty($row['archived']) && (int)$row['archived'] === 1) {
              echo "<span class='badge bg-dark'>Collected</span>";
            } elseif (empty($row['verification_id'])) {
              echo "<span class='badge bg-secondary'>No Questions</span>";
            } elseif (($row['v_status'] ?? '') === 'pending') {
              echo "<span class='badge bg-warning text-dark'>Waiting User</span>";
            } elseif (($row['v_status'] ?? '') === 'answered') {
              echo "<span class='badge bg-info'>Review</span>";
            } elseif (($row['v_status'] ?? '') === 'approved') {
              echo "<span class='badge bg-success'>Approved</span>";
            } elseif (($row['v_status'] ?? '') === 'rejected') {
              echo "<span class='badge bg-danger'>Rejected</span>";
            } else {
              echo "<span class='badge bg-secondary'>Unknown</span>";
            }
          ?>
        </td>

        <td>
          <?php if (empty($row['verification_id'])): ?>
            <a class="btn btn-primary btn-sm mb-1"
               href="send_verification.php?item_id=<?= (int)$row['id'] ?>">
               Send Questions
            </a>
          <?php endif; ?>

          <?php if (($row['v_status'] ?? '') === 'answered'): ?>
            <a class="btn btn-info btn-sm mb-1"
               href="review_verification.php?verification_id=<?= (int)$row['verification_id'] ?>">
               Review
            </a>

            <!-- ✅ EASY FIX: open separate page -->
            <a class="btn btn-warning btn-sm mb-1"
               href="request_more_info.php?verification_id=<?= (int)$row['verification_id'] ?>">
               Ask More
            </a>

            <a class="btn btn-success btn-sm mb-1"
               href="?approve=<?= (int)$row['verification_id'] ?>"
               onclick="return confirm('Approve this claim?');">Approve</a>

            <a class="btn btn-danger btn-sm mb-1"
               href="?reject=<?= (int)$row['verification_id'] ?>"
               onclick="return confirm('Reject this claim?');">Reject</a>
          <?php endif; ?>

          <a class="btn btn-outline-danger btn-sm"
             href="?delete=<?= (int)$row['id'] ?>"
             onclick="return confirm('Remove this item permanently?');">
             Remove
          </a>
        </td>
      </tr>
    <?php endwhile; ?>

    </tbody>
  </table>

  <div class="text-muted mt-3">
    <small>
      Notification sent to email.
    </small>
  </div>

</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html>