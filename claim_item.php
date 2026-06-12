<?php
// claim_item.php — user submits a claim for an item
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/notifications.php";

require_user(); // logged-in, role 'user'

$claimant_id = (int)($_SESSION["user_id"] ?? 0);
$sessionName = $_SESSION["name"] ?? "";
$error = "";

$item_id = (int)($_GET["item_id"] ?? $_POST["item_id"] ?? 0);
if ($item_id <= 0) {
    header("Location: browse_items.php");
    exit;
}

// Load the item
$stmt = $conn->prepare("SELECT id, user_id, item_name, item_type FROM lost_items WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    die("Item not found.");
}
// A user cannot claim an item they themselves reported
if ((int)$item["user_id"] === $claimant_id) {
    die("You reported this item, so you cannot claim it.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $claimant_name = trim($_POST["claimant_name"] ?? "");
    $contact_info  = trim($_POST["contact_info"] ?? "");
    $claim_details = trim($_POST["claim_details"] ?? "");

    if ($claimant_name === "" || $contact_info === "" || $claim_details === "") {
        $error = "All fields are required.";
    } elseif (mb_strlen($claim_details) < 15) {
        $error = "Please give more detail about the item (at least 15 characters).";
    } else {
        // Block a second open claim by the same user on the same item
        $dup = $conn->prepare("
            SELECT id FROM item_claims
            WHERE item_id = ? AND claimant_id = ?
              AND status NOT IN ('rejected','collected')
            LIMIT 1
        ");
        $dup->bind_param("ii", $item_id, $claimant_id);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            $error = "You already have an open claim for this item.";
        } else {
            $ins = $conn->prepare("
                INSERT INTO item_claims
                    (item_id, claimant_id, claimant_name, contact_info, claim_details, status)
                VALUES (?, ?, ?, ?, ?, 'submitted')
            ");
            $ins->bind_param("iisss", $item_id, $claimant_id, $claimant_name, $contact_info, $claim_details);

            if ($ins->execute()) {
                $newClaimId = (int)$conn->insert_id;

                // First entry in the audit/conversation trail
                $sys = $conn->prepare("
                    INSERT INTO claim_messages (claim_id, sender_role, sender_id, body)
                    VALUES (?, 'system', NULL, ?)
                ");
                $sysBody = "Claim submitted by {$claimant_name}.";
                $sys->bind_param("is", $newClaimId, $sysBody);
                $sys->execute();

                // Notify every active admin (in-app — kept on-site, no email)
                $admins = $conn->query("SELECT id FROM users WHERE role='admin' AND is_active=1");
                while ($ad = $admins->fetch_assoc()) {
                    add_notification(
                        (int)$ad["id"],
                        "New Item Claim",
                        "{$claimant_name} submitted a claim for '{$item['item_name']}'. Open Review Claims to manage it."
                    );
                }

                // Send the claimant straight to their claim so they can track it
                header("Location: claim_view.php?id=" . $newClaimId);
                exit;
            } else {
                $error = "Could not save your claim. Please try again.";
            }
        }
    }
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4" style="max-width:720px;">
  <h2 class="section-title">Claim This Item</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <p><b>Item:</b> <?= htmlspecialchars($item["item_name"]) ?>
       <span class="badge bg-info"><?= strtoupper(htmlspecialchars($item["item_type"])) ?></span></p>
    <p class="text-muted">
      Submit your claim. The admin will message you here to verify ownership before release.
    </p>

    <form method="POST" action="claim_item.php">
      <input type="hidden" name="item_id" value="<?= (int)$item["id"] ?>">

      <div class="mb-3">
        <label class="form-label">Your name</label>
        <input class="form-control" name="claimant_name"
               value="<?= htmlspecialchars($sessionName) ?>" required maxlength="255">
      </div>

      <div class="mb-3">
        <label class="form-label">Contact information</label>
        <input class="form-control" name="contact_info"
               placeholder="Phone or email where we can reach you" required maxlength="255">
      </div>

      <div class="mb-3">
        <label class="form-label">Information about the item</label>
        <textarea class="form-control" name="claim_details" rows="5" required minlength="15"
                  placeholder="Identifying details: marks, contents, where/when you lost it..."></textarea>
      </div>

      <button class="btn btn-primary">Submit Claim</button>
      <a href="browse_items.php" class="btn btn-outline-primary">Cancel</a>
    </form>
  </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>