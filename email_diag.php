<?php
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/mailer.php";

// Load claim_mailer only if it actually has content (avoids the empty-file trap)
$mailerFile = __DIR__ . "/claim_mailer.php";
$claimFnReady = false;
if (file_exists($mailerFile) && filesize($mailerFile) > 50) {
    require_once $mailerFile;
    $claimFnReady = function_exists('claim_status_email');
}

// 1) plain SMTP test
$to = trim($_GET["to"] ?? "");
$result = null;
if ($to !== "") {
    $result = send_email($to, "Test Recipient", "VU LostLink test email",
        "<p>Test email at " . date("Y-m-d H:i:s") . ".</p>");
}

// 2) claim email path
$claimId = (int)($_GET["claim"] ?? 0);
$status  = $_GET["status"] ?? "awaiting_response";
$claimTested = false; $claimRow = null;
if ($claimId > 0 && $claimFnReady) {
    $q = $conn->prepare("SELECT u.email, u.full_name FROM item_claims c JOIN users u ON u.id=c.claimant_id WHERE c.id=? LIMIT 1");
    $q->bind_param("i", $claimId); $q->execute();
    $claimRow = $q->get_result()->fetch_assoc();
    claim_status_email($conn, $claimId, $status);
    $claimTested = true;
}
function tail($file,$n=40){ return file_exists($file) ? htmlspecialchars(implode("", array_slice(file($file), -$n))) : "(file not created yet)"; }
?>
<!doctype html><html><head><meta charset="utf-8"><title>Email diagnostic</title></head>
<body style="font-family:Arial,sans-serif;max-width:860px;margin:30px auto;">
  <h2>Email diagnostic</h2>

  <p style="padding:8px;background:<?= $claimFnReady ? '#e7f7e7' : '#fde8e8' ?>;">
    claim_mailer.php loaded &amp; claim_status_email() ready:
    <b><?= $claimFnReady ? 'YES' : 'NO — claim_mailer.php is empty or missing; fix that file first' ?></b>
  </p>

  <h3>1. Plain SMTP test</h3>
  <form method="get">
    <input name="to" placeholder="your-email@example.com" value="<?= htmlspecialchars($to) ?>" size="40" style="padding:6px;">
    <button style="padding:6px 14px;">Send test</button>
  </form>
  <?php if ($result !== null): ?>
    <p style="padding:8px;background:<?= $result ? '#e7f7e7' : '#fde8e8' ?>;"><b>send_email() returned: <?= $result ? 'TRUE' : 'FALSE' ?></b></p>
  <?php endif; ?>

  <h3>2. Test the CLAIM email path directly</h3>
  <?php if (!$claimFnReady): ?>
    <p style="color:#b00;">Cannot test until claim_mailer.php has content (see banner above).</p>
  <?php else: ?>
    <form method="get">
      <input name="claim" placeholder="claim # e.g. 9" value="<?= $claimId ?: '' ?>" size="10" style="padding:6px;">
      <select name="status" style="padding:6px;">
        <?php foreach (["submitted","awaiting_response","verification","verified","ready_for_collection","collected","rejected"] as $s): ?>
          <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <button style="padding:6px 14px;">Fire claim email</button>
    </form>
    <?php if ($claimTested): ?>
      <p style="padding:8px;background:#eef;">
        Fired for claim #<?= $claimId ?>, status <b><?= htmlspecialchars($status) ?></b>.
        <?php if ($claimRow): ?>Claimant email on record: <b><?= htmlspecialchars($claimRow["email"] ?: "(empty!)") ?></b> (<?= htmlspecialchars($claimRow["full_name"] ?? "") ?>)<?php else: ?><b>No such claim found.</b><?php endif; ?>
      </p>
    <?php endif; ?>
  <?php endif; ?>

  <h3>claim_mailer.log (last 40 lines)</h3>
  <pre style="background:#0f1117;color:#9fe69f;padding:12px;white-space:pre-wrap;max-height:300px;overflow:auto;border-radius:6px;"><?= tail(__DIR__."/claim_mailer.log") ?></pre>

  <h3>mail_fallback.log (last 40 lines)</h3>
  <pre style="background:#0f1117;color:#9fe69f;padding:12px;white-space:pre-wrap;max-height:300px;overflow:auto;border-radius:6px;"><?= tail(__DIR__."/mail_fallback.log") ?></pre>
</body></html>