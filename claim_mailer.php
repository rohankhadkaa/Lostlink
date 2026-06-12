<?php
// claim_mailer.php — sends the claimant an email whenever their claim status changes.
require_once __DIR__ . "/mailer.php";

function claim_status_email(mysqli $conn, int $claim_id, string $status, string $note = ''): void {
    $st = $conn->prepare("
        SELECT c.id, c.claimant_name, li.item_name,
               u.full_name AS account_name, u.email AS account_email
        FROM item_claims c
        JOIN lost_items li ON li.id = c.item_id
        JOIN users u       ON u.id  = c.claimant_id
        WHERE c.id = ? LIMIT 1");
    $st->bind_param("i", $claim_id);
    $st->execute();
    $c = $st->get_result()->fetch_assoc();
    if (!$c || empty($c["account_email"])) {
        @file_put_contents(__DIR__ . "/claim_mailer.log",
            date("Y-m-d H:i:s") . "  claim=#{$claim_id}  status={$status}  SKIPPED (no claimant email on record)\n",
            FILE_APPEND);
        return;
    }

    $name = $c["claimant_name"] ?: $c["account_name"];
    $item = $c["item_name"];

    $map = [
      'submitted'            => ['Claim Received',
            'We have received your claim and it is awaiting review. No action is needed yet — we will keep you updated.'],
      'under_review'         => ['Claim Under Review',
            'An administrator is now reviewing your claim. No action is needed yet.'],
      'awaiting_response'    => ['Action Needed: Verification Questions',
            'An administrator has asked you verification questions. Please log in to VU LostLink, open My Claims, and answer them so your claim can proceed.'],
      'verification'         => ['Answers Received',
            'Thank you — your answers have been received and are being reviewed by an administrator. No action is needed right now.'],
      'verified'             => ['Ownership Verified',
            'Good news — your ownership has been verified. We will notify you as soon as the item is ready for collection.'],
      'ready_for_collection' => ['Ready for Collection',
            'Your item is ready to collect. Please bring valid student or staff ID to Level G (University Building) during operating hours (Mon-Fri, 9:00 AM - 4:00 PM).'],
      'collected'            => ['Item Collected',
            'Your item has been handed over and this claim is now complete. Thank you for using VU LostLink.'],
      'rejected'             => ['Claim Not Approved',
            'After review, this claim could not be approved.' . ($note !== '' ? ' Reason: ' . $note : '')],
    ];
    if (!isset($map[$status])) return;
    [$headline, $action] = $map[$status];

    $labels = [
      'submitted'=>'Claimed','under_review'=>'Under Review','verification'=>'Verification in Progress',
      'awaiting_response'=>'Awaiting Your Response','verified'=>'Verified',
      'ready_for_collection'=>'Ready for Collection','collected'=>'Collected','rejected'=>'Rejected',
    ];
    $statusLabel = $labels[$status] ?? ucfirst($status);

    $subject  = "VU LostLink - Claim #{$claim_id}: {$headline}";
    $eName    = htmlspecialchars($name);
    $eItem    = htmlspecialchars($item);
    $eAction  = htmlspecialchars($action);
    $eStatus  = htmlspecialchars($statusLabel);

    $body = "
      <div style='font-family:Arial,Helvetica,sans-serif;color:#222;max-width:560px;'>
        <h2 style='color:#0d6efd;margin:0 0 10px;'>VU LostLink</h2>
        <p>Hi {$eName},</p>
        <p>{$eAction}</p>
        <table style='border-collapse:collapse;margin:14px 0;font-size:14px;'>
          <tr><td style='padding:4px 14px 4px 0;color:#666;'>Claim</td><td><b>#{$claim_id}</b></td></tr>
          <tr><td style='padding:4px 14px 4px 0;color:#666;'>Item</td><td>{$eItem}</td></tr>
          <tr><td style='padding:4px 14px 4px 0;color:#666;'>Current status</td><td><b>{$eStatus}</b></td></tr>
        </table>
        <p style='color:#666;font-size:13px;'>Log in to VU LostLink and open <b>My Claims</b> to view full details or reply in the claim conversation.</p>
        <p style='color:#999;font-size:12px;'>This is an automated message from VU LostLink.</p>
      </div>";

    $ok = false;
    try {
        $ok = send_email($c["account_email"], $name, $subject, $body);
    } catch (\Throwable $e) {
        // never let an email failure break the claim workflow
    }
    @file_put_contents(__DIR__ . "/claim_mailer.log",
        date("Y-m-d H:i:s") . "  claim=#{$claim_id}  status={$status}  to={$c['account_email']}  result=" . ($ok ? "SENT" : "FAILED") . "\n",
        FILE_APPEND);
}