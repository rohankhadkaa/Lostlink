<?php
// claim_view.php — claim case view: status, structured verification, chat, admin actions
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/notifications.php";
require_once __DIR__ . "/audit.php";
require_once __DIR__ . "/claim_mailer.php";

require_login();

$uid     = (int)($_SESSION["user_id"] ?? 0);
$role    = strtolower(trim($_SESSION["role"] ?? ""));
$isAdmin = ($role === "admin");
$myRole  = $isAdmin ? 'admin' : 'user';

$claim_id = (int)($_GET["id"] ?? $_POST["claim_id"] ?? 0);
if ($claim_id <= 0) {
    header("Location: " . ($isAdmin ? "admin_claims.php" : "my_claims.php"));
    exit;
}

// ---- Load claim (+ item + claimant + reporter) ----
$st = $conn->prepare("
  SELECT c.*, li.item_name, li.item_type, li.picture,
         u.full_name AS account_name, u.email AS account_email,
         r.full_name AS reporter_name
  FROM item_claims c
  JOIN lost_items li ON li.id = c.item_id
  JOIN users u       ON u.id  = c.claimant_id
  LEFT JOIN users r  ON r.id  = li.user_id
  WHERE c.id = ? LIMIT 1
");
$st->bind_param("i", $claim_id);
$st->execute();
$claim = $st->get_result()->fetch_assoc();
if (!$claim) { die("Claim not found."); }

// ---- Access control: users may only touch their own claims ----
if (!$isAdmin && (int)$claim["claimant_id"] !== $uid) {
    http_response_code(403);
    die("Forbidden: you can only view your own claims.");
}

// ---- mark the OTHER party's chat messages as read for this viewer ----
$mark = $conn->prepare("
  UPDATE claim_messages SET read_at = NOW()
  WHERE claim_id = ? AND read_at IS NULL
    AND sender_role <> 'system' AND sender_role <> ?
");
$mark->bind_param("is", $claim_id, $myRole);
$mark->execute();

$STATUS_LABELS = [
  'submitted'            => 'Claimed',
  'under_review'         => 'Under Review',
  'verification'         => 'Verification in Progress',
  'awaiting_response'    => 'Awaiting Claimant Response',
  'verified'             => 'Verified',
  'ready_for_collection' => 'Ready for Collection',
  'collected'            => 'Collected',
  'rejected'             => 'Rejected',
];
$isClosed = in_array($claim["status"], ['collected','rejected'], true);

// ---- helpers ----
function post_message(mysqli $conn, int $claim_id, string $senderRole, ?int $senderId, string $body, ?string $image = null): void {
    $st = $conn->prepare("INSERT INTO claim_messages (claim_id, sender_role, sender_id, body, image) VALUES (?,?,?,?,?)");
    $st->bind_param("isiss", $claim_id, $senderRole, $senderId, $body, $image);
    $st->execute();
}
function set_status(mysqli $conn, int $claim_id, string $status): void {
    if ($status === 'collected') {
        $st = $conn->prepare("UPDATE item_claims SET status=?, collected_at=NOW() WHERE id=?");
    } elseif (in_array($status, ['under_review','verification','awaiting_response','verified','ready_for_collection'], true)) {
        $st = $conn->prepare("UPDATE item_claims SET status=?, reviewed_at=COALESCE(reviewed_at, NOW()) WHERE id=?");
    } else {
        $st = $conn->prepare("UPDATE item_claims SET status=? WHERE id=?");
    }
    $st->bind_param("si", $status, $claim_id);
    $st->execute();
}

// ---- AJAX: chat messages as JSON (polling) ----
if (isset($_GET["ajax"]) && $_GET["ajax"] === "messages") {
    header("Content-Type: application/json; charset=utf-8");
    $after = (int)($_GET["after"] ?? 0);
    $q = $conn->prepare("
      SELECT m.id, m.sender_role, m.sender_id, m.body, m.image, m.created_at, m.read_at,
             COALESCE(u.full_name,'') AS sender_name
      FROM claim_messages m
      LEFT JOIN users u ON u.id = m.sender_id
      WHERE m.claim_id = ? AND m.id > ?
      ORDER BY m.id ASC
    ");
    $q->bind_param("ii", $claim_id, $after);
    $q->execute();
    $res = $q->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = [
          "id"   => (int)$r["id"],
          "role" => $r["sender_role"],
          "name" => $r["sender_name"] !== '' ? $r["sender_name"] : ucfirst($r["sender_role"]),
          "body" => $r["body"],
          "img"  => $r["image"],
          "at"   => $r["created_at"],
          "read" => !empty($r["read_at"]),
          "mine" => ((int)$r["sender_id"] === $uid && $r["sender_role"] !== 'system'),
        ];
    }
    echo json_encode($out);
    exit;
}

// ---- POST handlers ----
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // --- chat message (both roles) ---
    if ($action === "send_message") {
        $body = trim($_POST["body"] ?? "");
        $image = null;
        if (!empty($_FILES["chat_image"]["name"]) && (($_FILES["chat_image"]["error"] ?? 1) === UPLOAD_ERR_OK)) {
            $allowed = ["jpg"=>1,"jpeg"=>1,"png"=>1,"webp"=>1];
            $ext  = strtolower(pathinfo($_FILES["chat_image"]["name"], PATHINFO_EXTENSION));
            $size = (int)$_FILES["chat_image"]["size"];
            if (isset($allowed[$ext]) && $size > 0 && $size <= 5*1024*1024) {
                if (!is_dir(__DIR__ . "/uploads")) { @mkdir(__DIR__ . "/uploads", 0775, true); }
                $fname = "claim{$claim_id}_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                if (move_uploaded_file($_FILES["chat_image"]["tmp_name"], __DIR__ . "/uploads/" . $fname)) {
                    $image = $fname;
                }
            }
        }
        if (($body !== "" || $image !== null) && !$isClosed) {
            post_message($conn, $claim_id, $role, $uid, $body, $image);
            log_audit($conn, $claim_id, $uid, 'message_sent', $isAdmin ? 'admin message' : 'claimant message');
            if ($isAdmin) {
                add_notification((int)$claim["claimant_id"], "New message on your claim",
                    "Admin sent a message about '{$claim['item_name']}'. Open your claim to reply.");
            } else {
                $admins = $conn->query("SELECT id FROM users WHERE role='admin' AND is_active=1");
                while ($ad = $admins->fetch_assoc()) {
                    add_notification((int)$ad["id"], "Claimant replied",
                        "A claimant replied on '{$claim['item_name']}'. Open Review Claims.");
                }
            }
        }
        header("Location: claim_view.php?id=" . $claim_id);
        exit;
    }

    // --- admin: send a round of structured verification questions ---
    if ($action === "send_questions" && $isAdmin && !$isClosed) {
        $clean = [];
        foreach ((array)($_POST["questions"] ?? []) as $qq) {
            $qq = trim($qq);
            if ($qq !== "") $clean[] = $qq;
        }
        if ($clean) {
            $nrStmt = $conn->prepare("SELECT COALESCE(MAX(round),0)+1 AS nr FROM claim_verifications WHERE claim_id=?");
            $nrStmt->bind_param("i", $claim_id);
            $nrStmt->execute();
            $nr = (int)$nrStmt->get_result()->fetch_assoc()["nr"];

            $insQ = $conn->prepare("INSERT INTO claim_verifications (claim_id, round, question, asked_by) VALUES (?,?,?,?)");
            foreach ($clean as $qtext) {
                $insQ->bind_param("iisi", $claim_id, $nr, $qtext, $uid);
                $insQ->execute();
            }
            set_status($conn, $claim_id, 'awaiting_response');
            post_message($conn, $claim_id, 'system', null, "Admin sent " . count($clean) . " verification question(s) (round {$nr}).");
            add_notification((int)$claim["claimant_id"], "Verification questions",
                "Admin asked " . count($clean) . " verification question(s) about '{$claim['item_name']}'. Please answer them.");
            claim_status_email($conn, $claim_id, 'awaiting_response');
            log_audit($conn, $claim_id, $uid, 'questions_sent', "round {$nr}: " . count($clean) . " question(s)");
        }
        header("Location: claim_view.php?id=" . $claim_id);
        exit;
    }

    // --- claimant: submit answers ---
    if ($action === "submit_answers" && !$isAdmin && !$isClosed) {
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
            // if the latest round is fully answered, move it to admin review
            $chk = $conn->prepare("
              SELECT COUNT(*) AS pending FROM claim_verifications
              WHERE claim_id=? AND round=(SELECT MAX(round) FROM claim_verifications WHERE claim_id=?)
                AND answer IS NULL
            ");
            $chk->bind_param("ii", $claim_id, $claim_id);
            $chk->execute();
            $pending = (int)$chk->get_result()->fetch_assoc()["pending"];
            if ($pending === 0) { set_status($conn, $claim_id, 'verification'); claim_status_email($conn, $claim_id, 'verification'); }

            post_message($conn, $claim_id, 'system', null, "Claimant submitted verification answers.");
            $admins = $conn->query("SELECT id FROM users WHERE role='admin' AND is_active=1");
            while ($ad = $admins->fetch_assoc()) {
                add_notification((int)$ad["id"], "Verification answered",
                    "A claimant answered verification questions on '{$claim['item_name']}'. Review the claim.");
            }
            log_audit($conn, $claim_id, $uid, 'answers_submitted', null);
        }
        header("Location: claim_view.php?id=" . $claim_id);
        exit;
    }

    // --- admin: status actions ---
    if ($isAdmin) {
        $next = null; $sysMsg = null; $nTitle = null; $nMsg = null;
        $reason = trim($_POST["rejection_reason"] ?? "");
        switch ($action) {
          case "start_review":
            if ($claim["status"] === 'submitted') { $next='under_review'; $sysMsg='Status changed to Under Review.'; }
            break;
          case "verify":
            if (in_array($claim["status"], ['under_review','verification','awaiting_response'], true)) {
                $next='verified';
                $sysMsg='Ownership verified by admin.';
                $nTitle='Ownership Verified';
                $nMsg="Your ownership of '{$claim['item_name']}' has been verified. You will be notified when the item is ready for collection.";
            }
            break;
          case "ready_collection":
            if ($claim["status"] === 'verified') {
                $next='ready_for_collection';
                $sysMsg='Marked Ready for Collection.';
                $nTitle='Ready for Collection';
                $nMsg="Your claim has been approved and verified. Please visit the Ground Floor Lost & Found Desk during operating hours to collect your item.";
            }
            break;
          case "collect":
            if ($claim["status"] === 'ready_for_collection') {
                $next='collected';
                $sysMsg='Item handed over and marked Collected.';
                $nTitle='Item Collected';
                $nMsg="You have collected '{$claim['item_name']}'. The claim is now closed.";
            }
            break;
          case "reject":
            if (!$isClosed) {
                $next='rejected';
                $sysMsg = 'Claim rejected by admin.' . ($reason !== '' ? " Reason: {$reason}" : '');
                $nTitle='Claim Rejected';
                $nMsg="Your claim for '{$claim['item_name']}' was rejected." . ($reason !== '' ? " Reason: {$reason}" : '') . " Contact the Lost & Found Desk if you disagree.";
            }
            break;
        }
        if ($next !== null) {
            set_status($conn, $claim_id, $next);
            if ($action === 'reject' && $reason !== '') {
                $rr = $conn->prepare("UPDATE item_claims SET rejection_reason=? WHERE id=?");
                $rr->bind_param("si", $reason, $claim_id);
                $rr->execute();
            }
            if ($action === 'collect') {
                $ar = $conn->prepare("UPDATE lost_items SET archived=1 WHERE id=?");
                $ar->bind_param("i", $claim["item_id"]);
                $ar->execute();
            }
            if ($sysMsg)  post_message($conn, $claim_id, 'system', null, $sysMsg);
            if ($action === 'ready_collection') {
                post_message($conn, $claim_id, 'system', null,
                  "Collection instructions: visit the Ground Floor Lost & Found Desk during operating hours to collect your item.");
            }
            if ($nTitle) add_notification((int)$claim["claimant_id"], $nTitle, $nMsg);
            claim_status_email($conn, $claim_id, $next, $reason ?? '');
            $auditAction = 'status_change';
            if ($action === 'reject')                $auditAction = 'rejected';
            elseif ($action === 'collect')           $auditAction = 'collected';
            elseif ($action === 'verify')            $auditAction = 'ownership_verified';
            elseif ($action === 'ready_collection')  $auditAction = 'ready_for_collection';
            log_audit($conn, $claim_id, $uid, $auditAction, $next . ($reason !== '' && $action==='reject' ? " ({$reason})" : ''));
        }
        header("Location: claim_view.php?id=" . $claim_id);
        exit;
    }
}

// ---- Chat thread (server-rendered) ----
$mq = $conn->prepare("
  SELECT m.id, m.sender_role, m.sender_id, m.body, m.image, m.created_at, m.read_at,
         COALESCE(u.full_name,'') AS sender_name
  FROM claim_messages m LEFT JOIN users u ON u.id = m.sender_id
  WHERE m.claim_id = ? ORDER BY m.id ASC
");
$mq->bind_param("i", $claim_id);
$mq->execute();
$messages = $mq->get_result();
$lastId = 0;

// ---- Audit history ----
$aq = $conn->prepare("
  SELECT a.action, a.detail, a.created_at, COALESCE(u.full_name, 'System') AS actor
  FROM claim_audit a LEFT JOIN users u ON u.id = a.user_id
  WHERE a.claim_id = ? ORDER BY a.id ASC
");
$aq->bind_param("i", $claim_id);
$aq->execute();
$audit = $aq->get_result();

// ---- Structured verification rounds ----
$vq = $conn->prepare("SELECT id, round, question, answer, asked_at, answered_at
                      FROM claim_verifications WHERE claim_id=? ORDER BY round ASC, id ASC");
$vq->bind_param("i", $claim_id);
$vq->execute();
$vres = $vq->get_result();
$rounds = [];
while ($v = $vres->fetch_assoc()) { $rounds[$v['round']][] = $v; }
$maxRound = $rounds ? max(array_keys($rounds)) : 0;
$pendingForClaimant = [];
if ($maxRound > 0) {
    foreach ($rounds[$maxRound] as $v) {
        if ($v['answer'] === null || $v['answer'] === '') $pendingForClaimant[] = $v;
    }
}

// ---- Progress tracker mapping ----
$stepLabels = ['Claimed','Under Review','Verification in Progress','Verified','Ready for Collection','Collected'];
$rejected = ($claim['status'] === 'rejected');
$reached = 0;
switch ($claim['status']) {
  case 'submitted':            $reached = 0; break;
  case 'under_review':         $reached = 1; break;
  case 'verification':         $reached = 2; break;
  case 'awaiting_response':    $reached = 2; break;
  case 'verified':             $reached = 3; break;
  case 'ready_for_collection': $reached = 4; break;
  case 'collected':            $reached = 5; break;
}

require_once __DIR__ . "/partials/header.php";
?>

<style>
  .chat-thread { max-height: 360px; overflow-y: auto; padding: .5rem; background:#f6f7fb; border-radius:.5rem; }
  .msg { margin-bottom:.6rem; display:flex; }
  .msg .bubble { max-width:75%; padding:.5rem .75rem; border-radius:.75rem; font-size:.95rem; }
  .msg.mine { justify-content:flex-end; }
  .msg.mine .bubble { background:#0d6efd; color:#fff; border-bottom-right-radius:.15rem; }
  .msg.theirs .bubble { background:#fff; border:1px solid #e2e6ea; border-bottom-left-radius:.15rem; }
  .msg.system { justify-content:center; }
  .msg.system .bubble { background:transparent; color:#6c757d; font-style:italic; font-size:.85rem; max-width:90%; text-align:center; }
  .chat-img { max-width:200px; max-height:200px; border-radius:.4rem; display:block; margin-top:.25rem; cursor:pointer; }
  .msg .meta { font-size:.72rem; opacity:.7; margin-bottom:.1rem; }
  .msg .seen { font-size:.68rem; opacity:.7; text-align:right; margin-top:.1rem; }
  .step { text-align:center; flex:1; font-size:.74rem; }
  .step .dot { width:26px;height:26px;border-radius:50%;line-height:26px;margin:0 auto .25rem;
               background:#dee2e6;color:#6c757d;font-weight:600; }
  .step.done .dot { background:#198754;color:#fff; }
  .step.current .dot { background:#0d6efd;color:#fff; }
  .audit-row { font-size:.85rem; padding:.25rem 0; border-bottom:1px solid #f0f0f0; }
  .vround { border:1px solid #e9ecef; border-radius:.5rem; padding:.6rem .8rem; margin-bottom:.6rem; }
  /* light text so details are readable on the dark theme */
  .container .card { color:#eef2f7; }
  .container .card .text-muted { color:#aab4c5 !important; }
  .chat-thread { color:#212529; }                 /* keep chat readable on its light bubbles */
  .chat-thread .text-muted { color:#6c757d !important; }
</style>

<div class="container mt-4" style="max-width:900px;">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="m-0"><?= $isAdmin ? "Claim #" . (int)$claim_id . ": " : "Claim: " ?><?= htmlspecialchars($claim["item_name"]) ?></h2>
    <a href="<?= $isAdmin ? 'admin_claims.php' : 'my_claims.php' ?>" class="btn btn-outline-primary">Back</a>
  </div>

  <!-- Details -->
  <div class="card mb-3">
    <div class="row g-3">
      <?php $showPhoto = ($isAdmin && !empty($claim["picture"])); ?>
      <?php if ($showPhoto): ?>
        <div class="col-md-3"><img src="uploads/<?= htmlspecialchars($claim["picture"]) ?>" alt="Item photo" class="img-fluid rounded border"></div>
      <?php endif; ?>
      <div class="col-md<?= $showPhoto ? '-9' : '-12' ?>">
        <div class="row g-2">
          <div class="col-md-4"><b>Item type:</b> <?= strtoupper(htmlspecialchars($claim["item_type"])) ?></div>
          <div class="col-md-4"><b>Reported by:</b> <?= htmlspecialchars($claim["reporter_name"] ?? '-') ?></div>
          <div class="col-md-4"><b>Claimant:</b> <?= htmlspecialchars($claim["claimant_name"] ?: $claim["account_name"]) ?></div>
          <div class="col-md-4"><b>Contact:</b> <?= htmlspecialchars($claim["contact_info"] ?? '-') ?></div>
          <div class="col-md-4"><b>Status:</b> <?= htmlspecialchars($STATUS_LABELS[$claim["status"]] ?? $claim["status"]) ?></div>
          <div class="col-12"><b>Details:</b> <?= nl2br(htmlspecialchars($claim["claim_details"])) ?></div>
          <?php if (!empty($claim["rejection_reason"])): ?>
            <div class="col-12 text-danger"><b>Rejection reason:</b> <?= htmlspecialchars($claim["rejection_reason"]) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Status tracker -->
  <div class="card mb-3">
    <?php if ($rejected): ?>
      <div class="alert alert-danger m-0">This claim was <b>Rejected</b>.</div>
    <?php else: ?>
      <div class="d-flex">
        <?php foreach ($stepLabels as $i => $label): ?>
          <?php $cls = ($i < $reached) ? 'done' : (($i === $reached) ? 'current' : ''); ?>
          <div class="step <?= $cls ?>">
            <div class="dot"><?= ($i <= $reached) ? '&#10003;' : ($i+1) ?></div>
            <?= htmlspecialchars($label) ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($claim["status"] === 'awaiting_response'): ?>
        <div class="text-center small text-primary mt-2">Awaiting Claimant Response</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Admin status actions -->
  <?php if ($isAdmin && !$isClosed): ?>
    <div class="card mb-3">
      <div class="d-flex gap-2 flex-wrap align-items-start">
        <?php if ($claim["status"] === 'submitted'): ?>
          <form method="POST"><input type="hidden" name="claim_id" value="<?= $claim_id ?>">
            <button name="action" value="start_review" class="btn btn-secondary btn-sm">Start Review</button></form>
        <?php endif; ?>
        <?php if (in_array($claim["status"], ['under_review','verification','awaiting_response'], true)): ?>
          <form method="POST" onsubmit="return confirm('Verify ownership for this claim?');">
            <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
            <button name="action" value="verify" class="btn btn-success btn-sm">Verify Ownership</button></form>
        <?php endif; ?>
        <?php if ($claim["status"] === 'verified'): ?>
          <form method="POST" onsubmit="return confirm('Mark Ready for Collection? The claimant will get pickup instructions.');">
            <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
            <button name="action" value="ready_collection" class="btn btn-success btn-sm">Ready for Collection</button></form>
        <?php endif; ?>
        <?php if ($claim["status"] === 'ready_for_collection'): ?>
          <form method="POST" onsubmit="return confirm('Confirm the item has been handed over?');">
            <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
            <button name="action" value="collect" class="btn btn-primary btn-sm">Mark Collected</button></form>
        <?php endif; ?>
        <form method="POST" class="d-flex gap-1" onsubmit="return confirm('Reject this claim?');">
          <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
          <input class="form-control form-control-sm" name="rejection_reason" placeholder="Reason (optional)" style="max-width:200px;">
          <button name="action" value="reject" class="btn btn-outline-danger btn-sm">Reject</button></form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Structured ownership verification -->
  <?php if ($isAdmin || (!$isClosed && $pendingForClaimant)): ?>
  <div class="card mb-3">
    <h5 class="mb-2">Ownership Verification</h5>

    <?php if ($isAdmin && !$isClosed): ?>
      <form method="POST" class="mb-3">
        <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
        <input type="hidden" name="action" value="send_questions">
        <label class="form-label">Verification questions (send a new round)</label>
        <div id="qlist">
          <input class="form-control mb-2" name="questions[]" placeholder="e.g. What color is the item?">
          <input class="form-control mb-2" name="questions[]" placeholder="e.g. What brand is it?">
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addQ()">+ Add question</button>
        <button class="btn btn-info btn-sm">Send Questions / Request More Info</button>
      </form>
    <?php endif; ?>

    <?php if (!$isAdmin && !$isClosed && $pendingForClaimant): ?>
      <form method="POST" class="mb-3">
        <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
        <input type="hidden" name="action" value="submit_answers">
        <p class="text-primary">Please answer the admin's verification questions:</p>
        <?php foreach ($pendingForClaimant as $pq): ?>
          <label class="form-label"><?= htmlspecialchars($pq['question']) ?></label>
          <textarea class="form-control mb-2" name="answers[<?= (int)$pq['id'] ?>]" rows="2" required></textarea>
        <?php endforeach; ?>
        <button class="btn btn-primary btn-sm">Submit Answers</button>
      </form>
    <?php endif; ?>

    <?php if ($isAdmin && $rounds): ?>
      <h6 class="text-muted">Verification history</h6>
      <?php foreach ($rounds as $rn => $qrows): ?>
        <div class="vround">
          <div class="mb-1"><b>Round <?= (int)$rn ?></b>
            <span class="text-muted small">asked <?= htmlspecialchars($qrows[0]['asked_at']) ?></span></div>
          <?php foreach ($qrows as $q): ?>
            <div class="ms-2 mb-2">
              <div><b>Q:</b> <?= htmlspecialchars($q['question']) ?></div>
              <?php if ($q['answer'] !== null && $q['answer'] !== ''): ?>
                <div class="text-success"><b>A:</b> <?= nl2br(htmlspecialchars($q['answer'])) ?>
                  <span class="text-muted small">(<?= htmlspecialchars($q['answered_at']) ?>)</span></div>
              <?php else: ?>
                <div class="text-warning small">Awaiting answer</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php elseif ($isAdmin): ?>
      <div class="text-muted small">No verification questions yet.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Free-form conversation (kept alongside structured verification) -->
  <div class="card mb-3">
    <h5 class="mb-2">Conversation</h5>
    <div id="thread" class="chat-thread">
      <?php while ($m = $messages->fetch_assoc()):
        $lastId = max($lastId, (int)$m["id"]);
        $isSys  = ($m["sender_role"] === 'system');
        $mine   = ((int)$m["sender_id"] === $uid && !$isSys);
        $cls    = $isSys ? 'system' : ($mine ? 'mine' : 'theirs');
        $name   = $m["sender_name"] !== '' ? $m["sender_name"] : ucfirst($m["sender_role"]);
      ?>
        <div class="msg <?= $cls ?>">
          <div class="bubble">
            <?php if (!$isSys): ?><div class="meta"><?= htmlspecialchars($name) ?> &middot; <?= htmlspecialchars($m["created_at"]) ?></div><?php endif; ?>
            <?= nl2br(htmlspecialchars($m["body"])) ?>
            <?php if (!empty($m["image"])): ?>
              <div class="mt-1"><a href="uploads/<?= htmlspecialchars($m["image"]) ?>" target="_blank" rel="noopener">
                <img src="uploads/<?= htmlspecialchars($m["image"]) ?>" alt="attachment" class="chat-img"></a></div>
            <?php endif; ?>
            <?php if ($mine && !empty($m["read_at"])): ?><div class="seen">Seen</div><?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
    <?php if (!$isClosed): ?>
      <form method="POST" enctype="multipart/form-data" class="mt-3">
        <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
        <input type="hidden" name="action" value="send_message">
        <div class="d-flex gap-2">
          <input class="form-control" name="body" placeholder="Type a message..." maxlength="2000" autocomplete="off">
          <button class="btn btn-primary">Send</button>
        </div>
        <div class="mt-2">
          <input type="file" class="form-control form-control-sm" name="chat_image" accept="image/jpeg,image/png,image/webp">
          <div class="form-text">Optional: attach a photo of the item (JPG, PNG or WEBP, max 5MB).</div>
        </div>
      </form>
    <?php else: ?>
      <div class="text-muted mt-3"><small>This claim is closed. The conversation is read-only.</small></div>
    <?php endif; ?>
  </div>

  <!-- Audit history -->
  <div class="card mb-3">
    <h5 class="mb-2">History</h5>
    <?php if ($audit && $audit->num_rows): ?>
      <?php while ($a = $audit->fetch_assoc()): ?>
        <div class="audit-row d-flex justify-content-between">
          <span><b><?= htmlspecialchars(ucwords(str_replace('_',' ', $a["action"]))) ?></b>
            <?= $a["detail"] ? '— ' . htmlspecialchars($a["detail"]) : '' ?>
            <span class="text-muted">by <?= htmlspecialchars($a["action"]==='collected' ? ($claim["claimant_name"] ?: $claim["account_name"]) : $a["actor"]) ?></span></span>
          <span class="text-muted"><?= htmlspecialchars($a["created_at"]) ?></span>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="text-muted small">No history recorded.</div>
    <?php endif; ?>
  </div>

</div>

<script>
  const claimId = <?= (int)$claim_id ?>;
  let lastId = <?= (int)$lastId ?>;
  const thread = document.getElementById('thread');

  function addQ(){
    const list=document.getElementById('qlist');
    const i=document.createElement('input');
    i.className='form-control mb-2'; i.name='questions[]'; i.placeholder='Another question...';
    list.appendChild(i);
  }
  function esc(s){return String(s??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');}
  function atBottom(){return thread.scrollHeight - thread.scrollTop - thread.clientHeight < 60;}
  function append(m){
    const wrap=document.createElement('div');
    let cls = m.role==='system' ? 'system' : (m.mine ? 'mine' : 'theirs');
    wrap.className='msg '+cls;
    const meta = m.role==='system' ? '' : `<div class="meta">${esc(m.name)} &middot; ${esc(m.at)}</div>`;
    const seen = (m.mine && m.read) ? `<div class="seen">Seen</div>` : '';
    const img = m.img ? `<div class="mt-1"><a href="uploads/${m.img}" target="_blank" rel="noopener"><img src="uploads/${m.img}" class="chat-img" alt="attachment"></a></div>` : '';
    wrap.innerHTML=`<div class="bubble">${meta}${esc(m.body).replaceAll('\n','<br>')}${img}${seen}</div>`;
    thread.appendChild(wrap);
  }
  async function poll(){
    try{
      const res=await fetch(`claim_view.php?id=${claimId}&ajax=messages&after=${lastId}`);
      const msgs=await res.json();
      if(msgs.length){
        const stick=atBottom();
        for(const m of msgs){append(m); lastId=m.id;}
        if(stick) thread.scrollTop=thread.scrollHeight;
      }
    }catch(e){}
  }
  if (thread){ thread.scrollTop=thread.scrollHeight; setInterval(poll, 4000); }
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>