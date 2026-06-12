<?php
// respond_verification.php — claimant interface to answer verification questions
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/notifications.php";
require_once __DIR__ . "/audit.php";
require_once __DIR__ . "/claim_mailer.php";

require_login();

$uid     = (int)($_SESSION["user_id"] ?? 0);
$isAdmin = (strtolower(trim($_SESSION["role"] ?? "")) === "admin");

$claim_id = (int)($_GET["claim_id"] ?? $_POST["claim_id"] ?? 0);
if ($claim_id <= 0) { header("Location: my_claims.php"); exit; }

// Load claim + item
$st = $conn->prepare("
  SELECT c.id, c.claimant_id, c.status, c.claim_details, li.item_name
  FROM item_claims c
  JOIN lost_items li ON li.id = c.item_id
  WHERE c.id = ? LIMIT 1
");
$st->bind_param("i", $claim_id);
$st->execute();
$claim = $st->get_result()->fetch_assoc();
if (!$claim) { die("Claim not found."); }

// Admins manage from the verification page; this interface is for the claimant.
if ($isAdmin) { header("Location: claim_view.php?id=" . $claim_id); exit; }

// Security: only the claimant who owns this claim may respond/view here.
if ((int)$claim["claimant_id"] !== $uid) {
    http_response_code(403);
    die("Forbidden: you can only respond to your own claims.");
}

$isClosed = in_array($claim["status"], ['collected','rejected'], true);
$flash = "";

// ---- Handle answer submission ----
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "submit_answers" && !$isClosed) {
    $savedAny = false;
    $upd = $conn->prepare("UPDATE claim_verifications SET answer=?, answered_at=NOW()
                           WHERE id=? AND claim_id=? AND answer IS NULL");
    foreach ((array)($_POST["answers"] ?? []) as $qid => $atext) {
        $atext = trim($atext);
        $qid = (int)$qid;
        if ($atext === "" || $qid <= 0) continue;
        $upd->bind_param("sii", $atext, $qid, $claim_id);
        $upd->execute();
        if ($upd->affected_rows > 0) $savedAny = true;
    }

    if ($savedAny) {
        // Status -> admin review
        $ss = $conn->prepare("UPDATE item_claims SET status='verification', reviewed_at=COALESCE(reviewed_at, NOW()) WHERE id=?");
        $ss->bind_param("i", $claim_id);
        $ss->execute();

        // Record a system note in the conversation thread
        $sm = $conn->prepare("INSERT INTO claim_messages (claim_id, sender_role, sender_id, body) VALUES (?, 'system', NULL, ?)");
        $body = "Claimant submitted verification answers.";
        $sm->bind_param("is", $claim_id, $body);
        $sm->execute();

        // Notify all admins
        $admins = $conn->query("SELECT id FROM users WHERE role='admin' AND is_active=1");
        while ($ad = $admins->fetch_assoc()) {
            add_notification((int)$ad["id"], "Verification answered",
                "A claimant answered verification questions on '{$claim['item_name']}'. The claim is now under admin review.");
        }
        log_audit($conn, $claim_id, $uid, 'answers_submitted', null);
        claim_status_email($conn, $claim_id, 'verification');
        $flash = "Your answers were submitted. Your claim is now under admin review.";
    } else {
        $flash = "No new answers were saved.";
    }
}

// ---- Load all rounds (history) + pending questions ----
$vq = $conn->prepare("SELECT id, round, question, answer, asked_at, answered_at
                      FROM claim_verifications WHERE claim_id=? ORDER BY round ASC, id ASC");
$vq->bind_param("i", $claim_id);
$vq->execute();
$vres = $vq->get_result();
$rounds = []; $pending = [];
while ($v = $vres->fetch_assoc()) {
    $rounds[$v['round']][] = $v;
    if ($v['answer'] === null || $v['answer'] === '') $pending[] = $v;
}

require_once __DIR__ . "/partials/header.php";
?>

<div class="container mt-4" style="max-width:720px;">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="m-0">Verification — <?= htmlspecialchars($claim["item_name"]) ?></h2>
    <a href="my_claims.php" class="btn btn-outline-primary">Back to My Claims</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <!-- Pending questions to answer -->
  <?php if (!$isClosed && $pending): ?>
    <div class="card mb-3">
      <h5 class="mb-2">Please answer these verification questions</h5>
      <p class="text-muted small mb-3">The admin needs these answers to confirm you're the owner.</p>
      <form method="POST">
        <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
        <input type="hidden" name="action" value="submit_answers">
        <?php foreach ($pending as $i => $pq): ?>
          <div class="mb-3">
            <label class="form-label"><b><?= ($i+1) ?>.</b> <?= htmlspecialchars($pq['question']) ?></label>
            <textarea class="form-control" name="answers[<?= (int)$pq['id'] ?>]" rows="2" required
                      placeholder="Your answer..."></textarea>
          </div>
        <?php endforeach; ?>
        <button class="btn btn-primary">Submit Answers</button>
      </form>
    </div>
  <?php elseif (!$isClosed): ?>
    <div class="alert alert-success">You have no questions awaiting your response right now.</div>
  <?php endif; ?>

  <!-- Full history (all previous questions & answers) -->
  <div class="card mb-3">
    <h5 class="mb-2">Verification history</h5>
    <?php if ($rounds): ?>
      <?php foreach ($rounds as $rn => $qrows): ?>
        <div class="border rounded p-2 mb-2">
          <div class="mb-1"><b>Round <?= (int)$rn ?></b>
            <span class="text-muted small">asked <?= htmlspecialchars($qrows[0]['asked_at']) ?></span></div>
          <?php foreach ($qrows as $q): ?>
            <div class="ms-2 mb-2">
              <div><b>Q:</b> <?= htmlspecialchars($q['question']) ?></div>
              <?php if ($q['answer'] !== null && $q['answer'] !== ''): ?>
                <div class="text-success"><b>A:</b> <?= nl2br(htmlspecialchars($q['answer'])) ?>
                  <span class="text-muted small">(<?= htmlspecialchars($q['answered_at']) ?>)</span></div>
              <?php else: ?>
                <div class="text-warning small">Not answered yet</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="text-muted small">No verification questions have been sent yet.</div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>