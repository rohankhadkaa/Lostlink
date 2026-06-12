<?php
require_once __DIR__ . "/config.php";

// Must be logged in
if (empty($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
// Must be admin
if (strtolower($_SESSION["role"] ?? "") !== "admin") {
    header("Location: dashboard.php");
    exit;
}

$search = trim($_GET["search"] ?? "");

// ---- Remove (delete) a user ----
if (isset($_GET["delete"])) {
    $uid = (int)$_GET["delete"];

    // can't delete yourself
    if ($uid === (int)$_SESSION["user_id"]) {
        header("Location: admin_users.php?err=self"); exit;
    }
    // don't allow removing the last admin (avoids lockout)
    $rq = $conn->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
    $rq->bind_param("i", $uid);
    $rq->execute();
    $target = $rq->get_result()->fetch_assoc();
    if ($target && $target["role"] === "admin") {
        $cnt = $conn->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch_assoc()["c"];
        if ((int)$cnt <= 1) {
            header("Location: admin_users.php?err=lastadmin"); exit;
        }
    }

    // ---- cascade: remove everything tied to this user so nothing is orphaned ----
    $hasClaims = $conn->query("SHOW TABLES LIKE 'item_claims'")->num_rows > 0;

    // items reported by this user
    $itemIds = [];
    $iq = $conn->prepare("SELECT id FROM lost_items WHERE user_id=?");
    $iq->bind_param("i", $uid); $iq->execute();
    $ir = $iq->get_result();
    while ($row = $ir->fetch_assoc()) { $itemIds[] = (int)$row["id"]; }

    if ($hasClaims) {
        // claims to remove = claims made BY the user + claims ON the user's items
        $claimIds = [];
        $cq = $conn->prepare("SELECT id FROM item_claims WHERE claimant_id=?");
        $cq->bind_param("i", $uid); $cq->execute();
        $cr = $cq->get_result();
        while ($row = $cr->fetch_assoc()) { $claimIds[] = (int)$row["id"]; }
        if ($itemIds) {
            $in = implode(",", array_map('intval', $itemIds));
            $cr2 = $conn->query("SELECT id FROM item_claims WHERE item_id IN ($in)");
            while ($row = $cr2->fetch_assoc()) { $claimIds[] = (int)$row["id"]; }
        }
        $claimIds = array_values(array_unique($claimIds));
        foreach ($claimIds as $cid) {
            foreach (["claim_messages","claim_verifications","claim_audit"] as $t) {
                $d = $conn->prepare("DELETE FROM $t WHERE claim_id=?");
                $d->bind_param("i", $cid); $d->execute();
            }
        }
        if ($claimIds) {
            $in = implode(",", array_map('intval', $claimIds));
            $conn->query("DELETE FROM item_claims WHERE id IN ($in)");
        }
    }

    // old-flow verifications + the items themselves
    if ($itemIds) {
        $in = implode(",", array_map('intval', $itemIds));
        $conn->query("DELETE FROM verifications WHERE item_id IN ($in)");
        $conn->query("DELETE FROM lost_items WHERE id IN ($in)");
    }

    // the user's notifications
    $dn = $conn->prepare("DELETE FROM notifications WHERE user_id=?");
    $dn->bind_param("i", $uid); $dn->execute();

    // finally remove the user
    $del = $conn->prepare("DELETE FROM users WHERE id=?");
    $del->bind_param("i", $uid);
    $del->execute();
    header("Location: admin_users.php?msg=deleted");
    exit;
}

// ---- Change role ----
if (isset($_POST["set_role"])) {
    $uid = (int)($_POST["user_id"] ?? 0);
    $newRole = strtolower(trim($_POST["role"] ?? "user"));
    if (!in_array($newRole, ["admin","user"], true)) $newRole = "user";

    if ($uid === (int)$_SESSION["user_id"]) {
        header("Location: admin_users.php?err=self"); exit;
    }

    $st = $conn->prepare("UPDATE users SET role=? WHERE id=?");
    $st->bind_param("si", $newRole, $uid);
    $st->execute();
    header("Location: admin_users.php");
    exit;
}

// ---- Query users ----
$sql = "SELECT id, full_name, email, role, is_active, created_at FROM users";
$params = []; $types = "";
if ($search !== "") {
    $sql .= " WHERE full_name LIKE ? OR email LIKE ?";
    $like = "%$search%";
    $params = [$like, $like]; $types = "ss";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

require_once __DIR__ . "/partials/header.php";

$err = $_GET["err"] ?? "";
$msg = $_GET["msg"] ?? "";
?>

<div class="container mt-4" style="max-width:1100px;">

  <h2 class="section-title">Manage Users</h2>

  <?php if ($err === "self"): ?>
    <div class="alert alert-warning">You can’t remove or change your own account.</div>
  <?php elseif ($err === "lastadmin"): ?>
    <div class="alert alert-warning">You can’t remove the last remaining admin.</div>
  <?php elseif ($msg === "deleted"): ?>
    <div class="alert alert-success">User removed.</div>
  <?php endif; ?>

  <div class="card">
    <form method="GET" class="mb-3">
      <div class="row g-2">
        <div class="col-md-10">
          <input class="form-control" name="search" placeholder="Search name or email..."
                 value="<?= htmlspecialchars($search) ?>">
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
            <th>Name</th>
            <th>Email</th>
            <th style="width:120px;">Role</th>
            <th style="width:120px;">Status</th>
            <th style="width:320px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while($u = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($u["full_name"]) ?></td>
              <td><?= htmlspecialchars($u["email"]) ?></td>
              <td><?= strtoupper(htmlspecialchars($u["role"])) ?></td>
              <td>
                <?php if ((int)$u["is_active"] === 1): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Disabled</span>
                <?php endif; ?>
              </td>
              <td class="d-flex gap-2 flex-wrap">
                <?php if ((int)$u["id"] !== (int)$_SESSION["user_id"]): ?>
                  <a class="btn btn-sm btn-outline-danger"
                     href="admin_users.php?delete=<?= (int)$u["id"] ?>"
                     onclick="return confirm('Permanently remove this user? This cannot be undone.');">
                     Remove
                  </a>
                <?php else: ?>
                  <span class="badge bg-info align-self-center">You</span>
                <?php endif; ?>

                <form method="POST" class="d-flex gap-2 align-items-center m-0">
                  <input type="hidden" name="user_id" value="<?= (int)$u["id"] ?>">
                  <select class="form-select form-select-sm" name="role" style="width:120px;">
                    <option value="user" <?= ($u["role"]==="user"?"selected":"") ?>>user</option>
                    <option value="admin" <?= ($u["role"]==="admin"?"selected":"") ?>>admin</option>
                  </select>
                  <button class="btn btn-sm btn-outline-primary" name="set_role" value="1">
                    Set Role
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">
    <a class="btn btn-outline-primary" href="admin_dashboard.php">Back to Admin Dashboard</a>
  </div>

</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>