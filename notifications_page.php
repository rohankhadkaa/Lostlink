<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_login(); // allow both user + admin to view their own notifications

$user_id = (int)($_SESSION["user_id"] ?? 0);
if ($user_id <= 0) { header("Location: login.php"); exit; }

// -------- Actions --------
if (isset($_GET["read"])) {
    $nid = (int)$_GET["read"];
    $st = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $st->bind_param("ii", $nid, $user_id);
    $st->execute();
    header("Location: notifications_page.php");
    exit;
}

if (isset($_GET["clear"])) {
    $nid = (int)$_GET["clear"];
    $st = $conn->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
    $st->bind_param("ii", $nid, $user_id);
    $st->execute();
    header("Location: notifications_page.php");
    exit;
}

if (isset($_GET["clear_all"])) {
    $st = $conn->prepare("DELETE FROM notifications WHERE user_id=?");
    $st->bind_param("i", $user_id);
    $st->execute();
    header("Location: notifications_page.php");
    exit;
}

if (isset($_GET["mark_all_read"])) {
    $st = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    $st->bind_param("i", $user_id);
    $st->execute();
    header("Location: notifications_page.php");
    exit;
}

// -------- Load notifications --------
$st = $conn->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$st->bind_param("i", $user_id);
$st->execute();
$list = $st->get_result();

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="m-0">Notifications</h2>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-primary" href="notifications_page.php?mark_all_read=1"
         onclick="return confirm('Mark all notifications as read?');">
        Mark All Read
      </a>

      <a class="btn btn-outline-danger" href="notifications_page.php?clear_all=1"
         onclick="return confirm('Clear ALL notifications? This cannot be undone.');">
        Clear All
      </a>
    </div>
  </div>

  <div class="card mt-3">
    <?php if ($list->num_rows === 0): ?>
      <div class="text-muted">No notifications yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered bg-white mb-0">
          <thead class="table-dark">
            <tr>
              <th style="width:18%;">Date</th>
              <th style="width:20%;">Title</th>
              <th>Message</th>
              <th style="width:18%;">Status</th>
              <th style="width:18%;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php while($n = $list->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($n["created_at"]) ?></td>
              <td><?= htmlspecialchars($n["title"]) ?></td>
              <td><?= htmlspecialchars($n["message"]) ?></td>
              <td>
                <?php if ((int)$n["is_read"] === 1): ?>
                  <span class="badge bg-secondary">Read</span>
                <?php else: ?>
                  <span class="badge bg-primary">New</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)$n["is_read"] === 0): ?>
                  <a class="btn btn-sm btn-outline-primary"
                     href="notifications_page.php?read=<?= (int)$n["id"] ?>">
                    Mark Read
                  </a>
                <?php endif; ?>

                <a class="btn btn-sm btn-outline-danger"
                   href="notifications_page.php?clear=<?= (int)$n["id"] ?>"
                   onclick="return confirm('Clear this notification?');">
                  Clear
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="mt-3">
    <?php
      $role = strtolower(trim($_SESSION["role"] ?? ""));
      if ($role === "admin") {
        echo '<a class="btn btn-outline-primary" href="admin_dashboard.php">Back to Dashboard</a>';
      } else {
        echo '<a class="btn btn-outline-primary" href="user_dashboard.php">Back to Dashboard</a>';
      }
    ?>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>